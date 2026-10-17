<?php

namespace App\Services;

use App\CustomerReceivableStatus;
use App\DeliveryRunStatus;
use App\Http\Resources\Api\V1\CustomerReceivableResource;
use App\Http\Resources\Api\V1\DeliveryRunResource;
use App\Http\Resources\Api\V1\WarehouseResource;
use App\Models\Customer;
use App\Models\CustomerCreditProfile;
use App\Models\CustomerReceivable;
use App\Models\DeliveryRun;
use App\Models\InventoryStock;
use App\Models\Order;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\SalesRoute;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\OrderStatus;
use App\PaymentTerm;
use App\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Data a device keeps locally to work without connection.
 *
 * "delta" resources return what changed after the cursor, including deletions;
 * "full" resources return the complete current set the user may see, which the
 * device replaces once it has read every page.
 */
class SyncPullService
{
    public const int DEFAULT_LIMIT = 200;

    public const int MAXIMUM_LIMIT = 500;

    /** Rows written in the last seconds are left for the next pull so none is skipped while still committing. */
    private const int SETTLE_SECONDS = 2;

    /** Orders the phone keeps per customer for its profile. */
    private const int RECENT_ORDER_DAYS = 30;

    /** Runs a driver still carries: loaded, on the road, or waiting to be settled. */
    private const array ACTIVE_RUN_STATUSES = [DeliveryRunStatus::Loaded, DeliveryRunStatus::InTransit, DeliveryRunStatus::AwaitingSettlement];

    public function __construct(private RouteActivityService $routeActivity, private ProductThumbnails $thumbnails) {}

    /** @return array<string, array{mode: string, available: bool}> */
    public function manifest(User $user): array
    {
        return collect($this->resources())
            ->map(fn (array $resource, string $name): array => [
                'mode' => $resource['mode'],
                'available' => $resource['allowed']($user),
            ])
            ->all();
    }

    public function exists(string $resource): bool
    {
        return array_key_exists($resource, $this->resources());
    }

    public function allows(User $user, string $resource): bool
    {
        return $this->resources()[$resource]['allowed']($user);
    }

    /** @return array{resource: string, mode: string, records: list<array<string, mixed>>, next_cursor: ?string, has_more: bool, server_time: string} */
    public function pull(User $user, string $resource, ?string $cursor, int $limit): array
    {
        $definition = $this->resources()[$resource];
        $limit = max(1, min($limit, self::MAXIMUM_LIMIT));

        [$models, $nextCursor] = $definition['mode'] === 'delta'
            ? $this->deltaPage($definition['query']($user), $cursor, $limit)
            : $this->fullPage($definition['query']($user), $cursor, $limit);
        $records = $definition['map']($models, $user);

        return [
            'resource' => $resource,
            'mode' => $definition['mode'],
            'records' => $records,
            'next_cursor' => $nextCursor,
            'has_more' => $models->count() === $limit,
            'server_time' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, array{mode: string, allowed: callable(User): bool, query: callable(User): Builder, map: callable(Collection<int, Model>, User): list<array<string, mixed>>}>
     */
    private function resources(): array
    {
        $takesOrders = fn (User $user): bool => Gate::forUser($user)->allows('manage-orders');
        // Who visits customers: the preventista taking orders and the driver delivering them.
        $onTheStreet = fn (User $user): bool => $takesOrders($user)
            || ($user->role === UserRole::Repartidor && Gate::forUser($user)->allows('execute-deliveries'));

        $assemblesRuns = fn (User $user): bool => Gate::forUser($user)->allows('manage-deliveries');
        $operatesInventory = fn (User $user): bool => Gate::forUser($user)->allows('operate-inventory');

        return [
            'warehouses' => [
                'mode' => 'full',
                'allowed' => $operatesInventory,
                'query' => fn (): Builder => Warehouse::query()->active(),
                'map' => fn (Collection $warehouses): array => WarehouseResource::collection($warehouses)->resolve(),
            ],
            'suppliers' => [
                'mode' => 'full',
                'allowed' => $operatesInventory,
                'query' => fn (): Builder => Supplier::query()->active(),
                'map' => fn (Collection $suppliers): array => $suppliers->map(fn (Supplier $supplier): array => ['id' => $supplier->id, 'code' => $supplier->code, 'name' => $supplier->name])->all(),
            ],
            'drivers' => [
                'mode' => 'full',
                'allowed' => $assemblesRuns,
                'query' => fn (): Builder => User::query()->where('is_active', true)->where('role', UserRole::Repartidor->value),
                'map' => fn (Collection $drivers): array => $drivers->map(fn (User $driver): array => ['id' => $driver->id, 'name' => $driver->name])->all(),
            ],
            'vehicles' => [
                'mode' => 'full',
                'allowed' => $assemblesRuns,
                'query' => fn (): Builder => Vehicle::query()->active(),
                'map' => fn (Collection $vehicles): array => $vehicles->map(fn (Vehicle $vehicle): array => ['id' => $vehicle->id, 'code' => $vehicle->code, 'license_plate' => $vehicle->license_plate, 'description' => $vehicle->description])->all(),
            ],
            // Confirmed orders waiting for a run: what the warehouse assembles runs from.
            'dispatch_orders' => [
                'mode' => 'full',
                'allowed' => $assemblesRuns,
                'query' => fn (): Builder => Order::query()
                    ->where('status', OrderStatus::Confirmed->value)
                    ->with(['salesRoute:id,code,name', 'items' => fn ($items) => $items->orderBy('created_at')->orderBy('id')]),
                'map' => fn (Collection $orders): array => $orders->map(fn (Order $order): array => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_id' => $order->customer_id,
                    'customer_name' => $order->customer_name,
                    'warehouse_id' => $order->warehouse_id,
                    'sales_route_id' => $order->sales_route_id,
                    'route_name' => $order->salesRoute?->name,
                    'route_visit_order' => $order->route_visit_order,
                    'requested_delivery_date' => $order->requested_delivery_date?->toDateString(),
                    'payment_term' => $order->payment_term->value,
                    'total' => $order->total,
                    'items' => $order->items->map(fn ($item): array => [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_presentation_id' => $item->product_presentation_id,
                        'product_name' => $item->product_name,
                        'presentation_name' => $item->presentation_name,
                        'quantity' => $item->quantity,
                        // The phone checks the stock in base units, as the load does.
                        'conversion_factor' => $item->conversion_factor,
                        'base_quantity' => $item->base_quantity,
                    ])->all(),
                ])->all(),
            ],
            'products' => [
                'mode' => 'delta',
                'allowed' => fn (User $user): bool => $user->is_active,
                'query' => fn (): Builder => Product::query()->withTrashed()->with([
                    'category:id,name',
                    'brand:id,name',
                    'baseUnit:id,name,symbol',
                    'presentations' => fn ($query) => $query->orderBy('conversion_factor')->with([
                        'priceTiers' => fn ($tiers) => $tiers->orderBy('min_quantity'),
                    ]),
                ]),
                'map' => fn (Collection $products): array => $products->map(fn (Product $product): array => $product->trashed()
                    ? ['id' => $product->id, 'deleted' => true]
                    : [
                        'id' => $product->id,
                        'deleted' => false,
                        'sku' => $product->sku,
                        'name' => $product->name,
                        'category' => $product->category?->name,
                        'brand' => $product->brand?->name,
                        'base_unit' => $product->baseUnit?->symbol,
                        'allows_decimal' => $product->allows_decimal,
                        'is_active' => $product->is_active,
                        'image_url' => $product->image_path ? asset('storage/'.$product->image_path) : null,
                        // The phone keeps the thumbnail on the device; a new version means a new photo.
                        'thumbnail_url' => $product->image_path ? route('api.v1.sync.product-images.show', $product) : null,
                        'image_version' => $this->thumbnails->version($product),
                        'presentations' => $product->presentations->map(fn (ProductPresentation $presentation): array => [
                            'id' => $presentation->id,
                            'name' => $presentation->name,
                            'barcode' => $presentation->barcode,
                            'conversion_factor' => $presentation->conversion_factor,
                            'sale_price' => $presentation->sale_price,
                            'is_base' => $presentation->is_base,
                            'is_sellable' => $presentation->is_sellable,
                            'is_active' => $presentation->is_active,
                            'price_tiers' => $presentation->priceTiers->map(fn (PriceTier $tier): array => [
                                'id' => $tier->id,
                                'min_quantity' => $tier->min_quantity,
                                'max_quantity' => $tier->max_quantity,
                                'unit_price' => $tier->unit_price,
                                'starts_at' => $tier->starts_at?->toDateString(),
                                'ends_at' => $tier->ends_at?->toDateString(),
                                'is_active' => $tier->is_active,
                            ])->all(),
                        ])->all(),
                        'updated_at' => $product->updated_at?->toIso8601String(),
                    ])->all(),
            ],
            'stocks' => [
                'mode' => 'delta',
                'allowed' => fn (User $user): bool => $user->is_active,
                'query' => fn (): Builder => InventoryStock::query(),
                'map' => fn (Collection $stocks): array => $stocks->map(fn (InventoryStock $stock): array => [
                    'id' => $stock->id,
                    'deleted' => false,
                    'warehouse_id' => $stock->warehouse_id,
                    'product_id' => $stock->product_id,
                    'quantity_on_hand' => $stock->quantity_on_hand,
                    'quantity_reserved' => $stock->quantity_reserved,
                    'quantity_available' => $stock->availableQuantity(),
                    'updated_at' => $stock->updated_at?->toIso8601String(),
                ])->all(),
            ],
            'routes' => [
                'mode' => 'full',
                'allowed' => $takesOrders,
                'query' => fn (User $user): Builder => SalesRoute::query()
                    ->active()
                    ->when($user->role === UserRole::Preventista, fn (Builder $query) => $query->where('salesperson_id', $user->id))
                    ->with(['stops' => fn ($stops) => $stops->where('is_active', true)->orderBy('visit_day')->orderBy('visit_order')]),
                'map' => fn (Collection $routes): array => $routes->map(fn (SalesRoute $route): array => [
                    'id' => $route->id,
                    'code' => $route->code,
                    'name' => $route->name,
                    'salesperson_id' => $route->salesperson_id,
                    'stops' => $route->stops->map(fn ($stop): array => [
                        'id' => $stop->id,
                        'customer_id' => $stop->customer_id,
                        'visit_day' => $stop->visit_day->value,
                        'visit_order' => $stop->visit_order,
                    ])->all(),
                ])->all(),
            ],
            'recent_orders' => [
                'mode' => 'full',
                'allowed' => $takesOrders,
                'query' => fn (User $user): Builder => Order::query()
                    ->whereIn('customer_id', $this->visibleCustomers($user)->select('customers.id'))
                    ->where('status', '!=', OrderStatus::Draft->value)
                    ->whereDate('order_date', '>=', today()->subDays(self::RECENT_ORDER_DAYS))
                    ->withCount('items')
                    ->with(['items' => fn ($items) => $items->orderBy('created_at')->orderBy('id')]),
                'map' => fn (Collection $orders): array => $orders->map(fn (Order $order): array => [
                    'id' => $order->id,
                    'customer_id' => $order->customer_id,
                    'route_stop_id' => $order->route_stop_id,
                    'client_reference' => $order->client_reference,
                    'order_number' => $order->order_number,
                    'order_date' => $order->order_date?->toDateString(),
                    'requested_delivery_date' => $order->requested_delivery_date?->toDateString(),
                    'status' => $order->status->value,
                    'status_label' => $order->status->label(),
                    'payment_term' => $order->payment_term->value,
                    'total' => $order->total,
                    'items_count' => (int) $order->items_count,
                    'salesperson_name' => $order->salesperson_name,
                    'notes' => $order->notes,
                    'cancellation_reason' => $order->cancellation_reason,
                    // Lines as they were sold, so the phone shows the order without signal.
                    'items' => $order->items->map(fn ($item): array => [
                        'product_id' => $item->product_id,
                        'product_presentation_id' => $item->product_presentation_id,
                        'product_sku' => $item->product_sku,
                        'product_name' => $item->product_name,
                        'presentation_name' => $item->presentation_name,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'line_total' => $item->line_total,
                        'notes' => $item->notes,
                    ])->all(),
                ])->all(),
            ],
            'route_activity' => [
                'mode' => 'full',
                'allowed' => fn (User $user): bool => $user->role === UserRole::Preventista && $takesOrders($user),
                'query' => fn (User $user): Builder => $this->routeActivity->query($user),
                'map' => fn (Collection $stops, User $user): array => $this->routeActivity->map($stops, $user),
            ],
            'customers' => [
                'mode' => 'full',
                'allowed' => $onTheStreet,
                'query' => fn (User $user): Builder => $this->visibleCustomers($user),
                'map' => fn (Collection $customers): array => $customers->map(fn (Customer $customer): array => [
                    'id' => $customer->id,
                    'code' => $customer->code,
                    'business_name' => $customer->business_name,
                    'business_type' => $customer->business_type,
                    'contact_name' => $customer->contact_name,
                    'phone' => $customer->phone,
                    'whatsapp' => $customer->whatsapp,
                    'address' => $customer->address,
                    'reference' => $customer->reference,
                    'latitude' => $customer->latitude,
                    'longitude' => $customer->longitude,
                ])->all(),
            ],
            'customer_credit' => [
                'mode' => 'full',
                'allowed' => $onTheStreet,
                'query' => fn (User $user): Builder => $this->visibleCustomers($user)->with('creditProfile'),
                'map' => fn (Collection $customers): array => $this->creditBalances($customers),
            ],
            'receivables' => [
                'mode' => 'full',
                'allowed' => fn (User $user): bool => $onTheStreet($user) && Gate::forUser($user)->allows('record-customer-payments'),
                'query' => fn (User $user): Builder => CustomerReceivable::query()
                    ->outstanding()
                    ->whereIn('customer_id', $this->visibleCustomers($user)->select('customers.id')),
                'map' => fn (Collection $receivables): array => CustomerReceivableResource::collection($receivables)->resolve(),
            ],
            'delivery_runs' => [
                'mode' => 'full',
                'allowed' => fn (User $user): bool => Gate::forUser($user)->allows('view-deliveries') && $user->role !== UserRole::Preventista,
                'query' => fn (User $user): Builder => DeliveryRun::query()
                    ->visibleTo($user)
                    // The warehouse also needs the runs it is still assembling and preparing.
                    ->whereIn('status', $user->role === UserRole::Repartidor ? self::ACTIVE_RUN_STATUSES : [DeliveryRunStatus::Draft, DeliveryRunStatus::Preparing, ...self::ACTIVE_RUN_STATUSES])
                    ->with([
                        'warehouse',
                        'driver',
                        'vehicle',
                        'runOrders' => fn ($query) => $query
                            ->with(['order', 'items', 'payments.receivedBy', 'payments.voidedBy', 'completedBy', 'preparedBy'])
                            ->orderBy('visit_order')
                            ->orderBy('id'),
                    ])
                    ->withCount('runOrders'),
                'map' => fn (Collection $runs): array => $runs
                    ->map(fn (DeliveryRun $run): array => (new DeliveryRunResource($run))->resolve())
                    ->all(),
            ],
        ];
    }

    private function visibleCustomers(User $user): Builder
    {
        // A driver only gets the customers of the runs they carry now, even if one was
        // deactivated after ordering: the merchandise still has to reach them.
        if ($user->role === UserRole::Repartidor) {
            return Customer::query()->carriedBy($user);
        }

        return Customer::query()
            ->active()
            ->visibleTo($user);
    }

    /**
     * Balances the device validates credit sales against while offline.
     *
     * @param  Collection<int, Customer>  $customers
     * @return list<array<string, mixed>>
     */
    private function creditBalances(Collection $customers): array
    {
        $ids = $customers->pluck('id');
        $today = today()->toDateString();
        $receivables = CustomerReceivable::query()
            ->whereIn('customer_id', $ids)
            ->whereIn('status', [CustomerReceivableStatus::Open->value, CustomerReceivableStatus::PartiallyPaid->value])
            ->groupBy('customer_id')
            ->selectRaw('customer_id, sum(outstanding_amount) as outstanding, sum(case when due_date < ? then outstanding_amount else 0 end) as overdue', [$today])
            ->get()
            ->keyBy('customer_id');
        $committed = Order::query()
            ->whereIn('customer_id', $ids)
            ->where('payment_term', PaymentTerm::Credit->value)
            ->whereIn('status', [OrderStatus::Confirmed->value, OrderStatus::Assigned->value, OrderStatus::Loaded->value, OrderStatus::InTransit->value])
            ->groupBy('customer_id')
            ->selectRaw('customer_id, sum(total) as committed')
            ->pluck('committed', 'customer_id');

        return $customers->map(function (Customer $customer) use ($receivables, $committed): array {
            /** @var CustomerCreditProfile|null $profile */
            $profile = $customer->creditProfile;
            $outstanding = (string) ($receivables->get($customer->id)?->outstanding ?? '0');
            $exposure = bcadd($outstanding, (string) ($committed[$customer->id] ?? '0'), 4);
            $limit = (string) ($profile?->credit_limit ?? '0');

            return [
                'customer_id' => $customer->id,
                'status' => $profile?->status->value ?? 'disabled',
                'allows_credit' => $profile?->status->allowsNewCredit() ?? false,
                'credit_limit' => $limit,
                'term_days' => $profile?->term_days ?? 0,
                'grace_days' => $profile?->grace_days ?? 0,
                'outstanding' => bcadd($outstanding, '0', 4),
                'overdue' => bcadd((string) ($receivables->get($customer->id)?->overdue ?? '0'), '0', 4),
                'committed' => bcadd((string) ($committed[$customer->id] ?? '0'), '0', 4),
                'available_credit' => bcsub($limit, $exposure, 4),
            ];
        })->all();
    }

    /**
     * Keyset page over (updated_at, id) for resources that report changes.
     *
     * @return array{0: Collection<int, Model>, 1: ?string}
     */
    private function deltaPage(Builder $query, ?string $cursor, int $limit): array
    {
        $table = $query->getModel()->getTable();
        [$updatedAt, $id] = $this->decodeCursor($cursor);
        $models = $query
            ->where("{$table}.updated_at", '<=', now()->subSeconds(self::SETTLE_SECONDS))
            ->when($updatedAt !== null, fn (Builder $page) => $page->where(function (Builder $after) use ($table, $updatedAt, $id): void {
                $after->where("{$table}.updated_at", '>', $updatedAt)
                    ->orWhere(fn (Builder $same) => $same->where("{$table}.updated_at", $updatedAt)->where("{$table}.id", '>', $id));
            }))
            ->orderBy("{$table}.updated_at")
            ->orderBy("{$table}.id")
            ->limit($limit)
            ->get();
        $last = $models->last();

        return [
            $models,
            $last === null ? $cursor : $this->encodeCursor($last->updated_at->format('Y-m-d H:i:s'), $last->getKey()),
        ];
    }

    /**
     * Page by id over the complete current set.
     *
     * @return array{0: Collection<int, Model>, 1: ?string}
     */
    private function fullPage(Builder $query, ?string $cursor, int $limit): array
    {
        $table = $query->getModel()->getTable();
        [, $id] = $this->decodeCursor($cursor);
        $models = $query
            ->when($id !== null, fn (Builder $page) => $page->where("{$table}.id", '>', $id))
            ->orderBy("{$table}.id")
            ->limit($limit)
            ->get();
        $last = $models->last();

        return [$models, $last === null ? null : $this->encodeCursor(null, $last->getKey())];
    }

    private function encodeCursor(?string $updatedAt, string $id): string
    {
        return rtrim(strtr(base64_encode(($updatedAt ?? '').'|'.$id), '+/', '-_'), '=');
    }

    /** @return array{0: ?string, 1: ?string} */
    private function decodeCursor(?string $cursor): array
    {
        if ($cursor === null || $cursor === '') {
            return [null, null];
        }

        $decoded = base64_decode(strtr($cursor, '-_', '+/'), true);

        if ($decoded === false || ! str_contains($decoded, '|')) {
            return [null, null];
        }

        [$updatedAt, $id] = explode('|', $decoded, 2);

        if ($updatedAt !== '') {
            try {
                $updatedAt = CarbonImmutable::parse($updatedAt)->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                return [null, null];
            }
        }

        return [$updatedAt === '' ? null : $updatedAt, $id === '' ? null : $id];
    }
}
