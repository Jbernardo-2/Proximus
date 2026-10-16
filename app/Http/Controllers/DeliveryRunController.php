<?php

namespace App\Http\Controllers;

use App\Actions\CreateDeliveryRunAction;
use App\Actions\UpdateDeliveryRunAction;
use App\DeliveryOutcomeReason;
use App\DeliveryRunStatus;
use App\Http\Requests\StoreDeliveryRunRequest;
use App\Http\Requests\UpdateDeliveryRunRequest;
use App\Models\DeliveryRun;
use App\Models\InventoryStock;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SalesRoute;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\OrderStatus;
use App\PaymentTerm;
use App\Services\DeliveryInvoiceService;
use App\Services\DeliveryLoadShortages;
use App\Services\DeliveryRunAccountPayments;
use App\SettlementMethod;
use App\UserRole;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DeliveryRunController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', DeliveryRun::class);
        /** @var User $user */
        $user = $request->user();
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->toString();
        $dateFrom = $request->string('date_from')->toString();
        $dateTo = $request->string('date_to')->toString();
        $warehouses = Warehouse::query()->active()->orderByDesc('is_default')->orderBy('name')->get();
        $warehouseId = $request->string('warehouse_id')->toString();
        $warehouseId = $warehouses->contains('id', $warehouseId)
            ? $warehouseId
            : (string) ($warehouses->firstWhere('is_default', true)?->id ?? $warehouses->first()?->id ?? '');
        $routeId = $request->string('sales_route_id')->toString();
        $planningDate = $request->string('planning_date')->toString();
        $baseQuery = DeliveryRun::query()->visibleTo($user);
        $history = $request->boolean('historial');
        $closedStatuses = [DeliveryRunStatus::Settled->value, DeliveryRunStatus::Cancelled->value];
        $openStatuses = collect(DeliveryRunStatus::cases())->filter->isOpen()->map->value->values()->all();
        $pendingOrderBase = Order::query()
            ->where('status', OrderStatus::Confirmed)
            ->when($warehouseId !== '', fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->when($routeId !== '', fn ($query) => $query->where('sales_route_id', $routeId))
            ->when($this->isDate($planningDate), fn ($query) => $query->whereDate('requested_delivery_date', $planningDate));
        $pendingOrders = (clone $pendingOrderBase)
            ->with(['customer', 'salesRoute'])
            ->withCount('items')
            ->orderByRaw('requested_delivery_date is null')
            ->orderBy('requested_delivery_date')
            ->orderBy('route_visit_order')

            ->orderBy('order_number')
            ->paginate(10, ['*'], 'orders_page')
            ->withQueryString();
        $consolidated = OrderItem::query()
            ->whereIn('order_id', (clone $pendingOrderBase)->select('orders.id'))
            ->selectRaw('product_id, min(product_name) as product_name, min(product_sku) as product_sku, min(base_unit_symbol) as base_unit_symbol, sum(base_quantity) as total_base_quantity, count(distinct order_id) as orders_count, count(*) as lines_count')
            ->groupBy('product_id')
            ->orderBy('product_name')
            ->paginate(20, ['*'], 'consolidated_page')
            ->withQueryString();
        $consolidatedProductIds = $consolidated->getCollection()->pluck('product_id');
        $presentationTotals = OrderItem::query()
            ->whereIn('order_id', (clone $pendingOrderBase)->select('orders.id'))
            ->whereIn('product_id', $consolidatedProductIds)
            ->selectRaw('product_id, product_presentation_id, min(presentation_name) as presentation_name, sum(quantity) as total_quantity, sum(base_quantity) as total_base_quantity')
            ->groupBy('product_id', 'product_presentation_id')
            ->orderByDesc('total_base_quantity')
            ->get()
            ->groupBy('product_id');
        $consolidatedProducts = Product::query()
            ->withTrashed()
            ->with(['category:id,name', 'brand:id,name'])
            ->whereIn('id', $consolidatedProductIds)
            ->get()
            ->keyBy('id');
        $planningStocks = $warehouseId === '' ? collect() : InventoryStock::query()
            ->where('warehouse_id', $warehouseId)
            ->whereIn('product_id', $consolidatedProductIds)
            ->get()
            ->keyBy('product_id');

        return view('deliveries.index', [
            'deliveryRuns' => (clone $baseQuery)
                ->with(['warehouse', 'driver', 'vehicle'])
                ->withCount('runOrders')
                ->when($search !== '', fn ($query) => $query->where(function ($builder) use ($search): void {
                    $builder->where('run_number', 'like', "%{$search}%")
                        ->orWhere('driver_name', 'like', "%{$search}%")
                        ->orWhere('vehicle_license_plate', 'like', "%{$search}%")
                        ->orWhereHas('runOrders.order', fn ($orders) => $orders
                            ->where('order_number', 'like', "%{$search}%")
                            ->orWhere('customer_name', 'like', "%{$search}%"));
                }))
                // The board shows the open runs; settled and cancelled ones live in the history.
                ->whereIn('status', $history ? $closedStatuses : $openStatuses)
                ->when(in_array($status, $history ? $closedStatuses : $openStatuses, true), fn ($query) => $query->where('status', $status))
                ->when($this->isDate($dateFrom), fn ($query) => $query->whereDate('scheduled_date', '>=', $dateFrom))
                ->when($this->isDate($dateTo), fn ($query) => $query->whereDate('scheduled_date', '<=', $dateTo))
                ->orderByDesc('scheduled_date')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate($history ? 20 : 100, ['*'], 'runs_page')
                ->withQueryString(),
            'history' => $history,
            'settledRun' => DeliveryRun::query()->find($request->session()->get('settled_run_id')),
            'metrics' => [

                'pending_orders' => (clone $pendingOrderBase)->count(),
                'pending_total' => (string) (clone $pendingOrderBase)->sum('total'),
            ],
            'statuses' => array_map(DeliveryRunStatus::from(...), $history ? $closedStatuses : $openStatuses),
            'search' => $search,
            'selectedStatus' => $status,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'pendingOrders' => $pendingOrders,
            'consolidated' => $consolidated,
            'presentationTotals' => $presentationTotals,
            'consolidatedProducts' => $consolidatedProducts,
            'planningStocks' => $planningStocks,
            'warehouses' => $warehouses,
            'salesRoutes' => SalesRoute::query()->active()->orderBy('name')->get(['id', 'code', 'name']),
            'selectedWarehouseId' => $warehouseId,
            'selectedRouteId' => $routeId,
            'planningDate' => $planningDate,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', DeliveryRun::class);

        return view('deliveries.form', [
            'deliveryRun' => new DeliveryRun(['scheduled_date' => today()->toDateString()]),
            ...$this->formOptions(),
        ]);
    }

    public function store(
        StoreDeliveryRunRequest $request,
        CreateDeliveryRunAction $createDeliveryRun,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $deliveryRun = $createDeliveryRun->handle($request->validated(), $user);

        return redirect()->route('delivery-runs.show', $deliveryRun)
            ->with('success', 'Jornada creada. Ahora asigna los pedidos confirmados.');
    }

    public function show(Request $request, DeliveryRun $deliveryRun, DeliveryInvoiceService $invoiceService, DeliveryLoadShortages $shortages, DeliveryRunAccountPayments $accountPayments): View
    {
        Gate::authorize('view', $deliveryRun);
        /** @var User $user */
        $user = $request->user();
        $deliveryRun->load([
            'warehouse',
            'driver',
            'vehicle',
            'creator',
            'preparationStartedBy',
            'loadedBy',
            'departedBy',
            'settledBy',
            'cancelledBy',
            'runOrders' => fn ($query) => $query
                ->with([
                    'order.customer',
                    'order.salesRoute.salesperson',
                    'order.salesperson',
                    'items.product',
                    'items.presentation',
                    'payments.receivedBy',
                    'payments.voidedBy',
                    'settlement.lines.deliveryPayment',
                    'settlement.lines.receivable',
                    'settlement.settledBy',
                    'completedBy',
                    'outcomeRecordedBy',
                    'preparedBy',
                ])
                ->orderBy('visit_order')
                ->orderBy('id'),
            'statusHistory' => fn ($query) => $query->with('changedBy')->latest()->orderByDesc('id'),
        ])->loadCount('runOrders');
        $allItems = $deliveryRun->runOrders->flatMap->items;
        $cashOrdersNeedingCreditReason = $deliveryRun->runOrders
            ->filter(fn ($runOrder): bool => $runOrder->order->payment_term === PaymentTerm::Cash
                && bccomp((string) $runOrder->balance_due, '0', 4) > 0
                && blank($runOrder->credit_reason));
        $pickingSummary = $allItems
            ->groupBy('product_presentation_id')
            ->map(function ($items): array {
                $first = $items->first();

                return [
                    'product_id' => $first->product_id,
                    'product_name' => $first->product_name,
                    'product_sku' => $first->product_sku,
                    'presentation_name' => $first->presentation_name,
                    'base_unit_symbol' => $first->base_unit_symbol,
                    'requested_quantity' => $items->reduce(
                        fn (string $total, $item): string => bcadd($total, (string) $item->requested_quantity, 6),
                        '0.000000',
                    ),
                    'prepared_quantity' => $items->reduce(
                        fn (string $total, $item): string => bcadd($total, (string) $item->prepared_quantity, 6),
                        '0.000000',
                    ),
                    'loaded_quantity' => $items->reduce(
                        fn (string $total, $item): string => bcadd($total, (string) $item->loaded_quantity, 6),
                        '0.000000',
                    ),
                ];
            })
            ->sortBy(fn (array $row): string => $row['product_name'].'|'.$row['presentation_name'])
            ->values();
        $canAssign = Gate::forUser($user)->allows('assignOrders', $deliveryRun);
        $reportRoutes = $deliveryRun->runOrders
            ->pluck('order.salesRoute')
            ->filter()
            ->unique('id')
            ->filter(fn (SalesRoute $salesRoute): bool => Gate::forUser($user)
                ->allows('viewRouteReport', [$deliveryRun, $salesRoute]))
            ->sortBy('name')
            ->values();
        $invoicedRunOrder = $deliveryRun->runOrders
            ->firstWhere('id', $request->session()->get('delivery_invoice_run_order_id'));
        // Cash the driver must hand over: deliveries plus the old debts collected on the
        // way. Stored totals are frozen at settlement; before that, account payments are live.
        $accountPaymentEntries = $accountPayments->for($deliveryRun);
        $accountPaymentMethods = $accountPayments->byMethod($accountPaymentEntries);
        $deliveryCash = bcsub((string) $deliveryRun->cash_expected, (string) $deliveryRun->account_payment_cash, 4);
        $accountPaymentCash = $deliveryRun->status === DeliveryRunStatus::Settled
            ? (string) $deliveryRun->account_payment_cash
            : bcadd($accountPaymentMethods['cash'] ?? '0', '0', 4);
        $returns = $allItems
            ->groupBy(fn ($item): string => $item->product_id.'|'.$item->presentation_name)
            ->map(fn ($items): array => [
                'product_name' => $items->first()->product_name,
                'presentation_name' => $items->first()->presentation_name,
                'returned' => $items->reduce(fn (string $sum, $item): string => bcadd($sum, (string) $item->returned_quantity, 6), '0'),
                'damaged' => $items->reduce(fn (string $sum, $item): string => bcadd($sum, (string) $item->damaged_quantity, 6), '0'),
                'missing' => $items->reduce(fn (string $sum, $item): string => bcadd($sum, (string) $item->missing_quantity, 6), '0'),
            ])
            ->filter(fn (array $row): bool => bccomp(bcadd(bcadd($row['returned'], $row['damaged'], 6), $row['missing'], 6), '0', 6) > 0)
            ->sortBy('product_name')
            ->values();

        return view('deliveries.show', [
            'deliveryRun' => $deliveryRun,
            'accountPaymentEntries' => $accountPaymentEntries,
            'accountPaymentMethods' => $accountPaymentMethods,
            'deliveryCash' => $deliveryCash,
            'accountPaymentCash' => $accountPaymentCash,
            'cashToReceive' => bcadd($deliveryCash, $accountPaymentCash, 4),
            'returns' => $returns,
            'invoiceDialog' => $invoicedRunOrder?->settlement === null
                ? null
                : $invoiceService->dialogData($invoicedRunOrder),
            'cashOrdersNeedingCreditReason' => $cashOrdersNeedingCreditReason,
            'candidateOrders' => $canAssign ? Order::query()
                ->where('status', OrderStatus::Confirmed)
                ->where('warehouse_id', $deliveryRun->warehouse_id)
                ->with(['customer', 'salesRoute', 'items'])
                ->orderByRaw('requested_delivery_date is null')
                ->orderBy('requested_delivery_date')
                ->orderBy('route_visit_order')
                ->orderBy('order_number')
                ->get() : collect(),
            'pickingSummary' => $pickingSummary,
            'loadShortages' => $deliveryRun->status === DeliveryRunStatus::Preparing ? $shortages->for($deliveryRun) : collect(),
            'reportRoutes' => $reportRoutes,
            'inventoryStocks' => InventoryStock::query()
                ->where('warehouse_id', $deliveryRun->warehouse_id)
                ->whereIn('product_id', $allItems->pluck('product_id')->unique())
                ->get()
                ->keyBy('product_id'),
            'canUpdate' => Gate::forUser($user)->allows('update', $deliveryRun),
            'canAssign' => $canAssign,
            'canStartPreparation' => Gate::forUser($user)->allows('startPreparation', $deliveryRun),
            'canPrepare' => Gate::forUser($user)->allows('prepare', $deliveryRun),
            'canLoad' => Gate::forUser($user)->allows('load', $deliveryRun),
            'canDepart' => Gate::forUser($user)->allows('depart', $deliveryRun),
            'canExecute' => Gate::forUser($user)->allows('execute', $deliveryRun),
            'canSettle' => Gate::forUser($user)->allows('settle', $deliveryRun),
            'canCancel' => Gate::forUser($user)->allows('cancel', $deliveryRun),
            'canVoidPayments' => $user->canSettleDeliveries() && $deliveryRun->status->isOpen(),
            'outcomeReasons' => DeliveryOutcomeReason::cases(),
            'settlementMethods' => SettlementMethod::cases(),
        ]);
    }

    public function edit(DeliveryRun $deliveryRun): View
    {
        Gate::authorize('update', $deliveryRun);

        return view('deliveries.form', [
            'deliveryRun' => $deliveryRun,
            ...$this->formOptions(),
        ]);
    }

    public function update(
        UpdateDeliveryRunRequest $request,
        DeliveryRun $deliveryRun,
        UpdateDeliveryRunAction $updateDeliveryRun,
    ): RedirectResponse {
        $updateDeliveryRun->handle($deliveryRun, $request->validated());

        return redirect()->route('delivery-runs.show', $deliveryRun)
            ->with('success', 'Jornada actualizada correctamente.');
    }

    private function formOptions(): array
    {
        return [
            'warehouses' => Warehouse::query()->active()->orderByDesc('is_default')->orderBy('name')->get(),
            'drivers' => User::query()
                ->where('role', UserRole::Repartidor)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'vehicles' => Vehicle::query()->active()->orderBy('description')->get(),
        ];
    }

    private function isDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
