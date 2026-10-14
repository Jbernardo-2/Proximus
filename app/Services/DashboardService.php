<?php

namespace App\Services;

use App\CustomerAccountEntryStatus;
use App\CustomerAccountEntryType;
use App\DeliveryOrderStatus;
use App\DeliveryPaymentStatus;
use App\DeliveryRunStatus;
use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Models\CustomerReceivable;
use App\Models\DeliveryPayment;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunOrder;
use App\Models\InventoryStock;
use App\Models\Order;
use App\Models\SalesRoute;
use App\Models\User;
use App\OrderStatus;
use App\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class DashboardService
{
    /**
     * @return array{
     *     eyebrow: string,
     *     title: string,
     *     description: string,
     *     cards: list<array{label: string, value: int|string, hint: string, tone: string, url: string, format?: string}>,
     *     sections: list<array{key: string, title: string, description: string, empty: string, items: list<array{title: string, meta: string, badge: string, tone: string, url: string, action: string, priority: string}>}>,
     *     trend: array{labels: list<string>, orders: list<int>, sales: list<float>}
     * }
     */
    public function forUser(User $user): array
    {
        return match ($user->role) {
            UserRole::Bodeguero => $this->warehouseDashboard(),
            UserRole::Preventista => $this->salesDashboard($user),
            default => throw new \LogicException('El perfil no utiliza este tablero.'),
        };
    }

    /**
     * @return array{
     *     today: array{sold: string, deliveries: int, collected: string, collected_on_delivery: string, collected_on_account: string, credit: string, credit_deliveries: int},
     *     receivables: array{outstanding: string, overdue: string, overdue_customers: int},
     *     pending: list<array{label: string, critical: bool, count: int, url: string}>,
     *     runs: Collection<int, DeliveryRun>,
     *     trend: array{labels: list<string>, sold: list<float>, collected: list<float>},
     *     unassignedOrders: Collection<int, Order>,
     *     overdueReceivables: Collection<int, CustomerReceivable>
     * }
     */
    public function management(): array
    {
        $today = CarbonImmutable::today();
        $tomorrow = $today->addDay();
        $completedToday = DeliveryRunOrder::query()
            ->where('completed_at', '>=', $today)
            ->where('completed_at', '<', $tomorrow);
        $collectedOnDelivery = (string) $this->activeDeliveryPayments()
            ->where('received_at', '>=', $today)
            ->where('received_at', '<', $tomorrow)
            ->sum('amount');
        $collectedOnAccount = (string) $this->activeCustomerPayments()
            ->where('occurred_at', '>=', $today)
            ->where('occurred_at', '<', $tomorrow)
            ->sum('credit_amount');
        $overdue = CustomerReceivable::query()->overdue();
        $completedStatuses = collect(DeliveryOrderStatus::cases())
            ->filter(fn (DeliveryOrderStatus $status): bool => $status->isCompleted())
            ->map->value
            ->all();

        return [
            'today' => [
                'sold' => (string) (clone $completedToday)->sum('delivered_total'),
                'deliveries' => (clone $completedToday)->where('delivered_total', '>', 0)->count(),
                'collected' => bcadd($collectedOnDelivery, $collectedOnAccount, 4),
                'collected_on_delivery' => $collectedOnDelivery,
                'collected_on_account' => $collectedOnAccount,
                'credit' => (string) (clone $completedToday)->sum('balance_due'),
                'credit_deliveries' => (clone $completedToday)->where('balance_due', '>', 0)->count(),
            ],
            'receivables' => [
                'outstanding' => (string) CustomerReceivable::query()->outstanding()->sum('outstanding_amount'),
                'overdue' => (string) (clone $overdue)->sum('outstanding_amount'),
                'overdue_customers' => (clone $overdue)->distinct()->count('customer_id'),
            ],
            'pending' => [
                [
                    'label' => 'Pedidos sin asignar',
                    'critical' => false,
                    'count' => Order::query()->where('status', OrderStatus::Confirmed)->count(),
                    'url' => route('orders.index', ['status' => OrderStatus::Confirmed->value], false),
                ],
                [
                    'label' => 'Jornadas por liquidar',
                    'critical' => false,
                    'count' => DeliveryRun::query()->where('status', DeliveryRunStatus::AwaitingSettlement)->count(),
                    'url' => route('delivery-runs.index', ['status' => DeliveryRunStatus::AwaitingSettlement->value], false),
                ],
                [
                    'label' => 'Productos con faltante',
                    'critical' => true,
                    'count' => $this->inventoryShortagesQuery()->count(),
                    'url' => route('inventory.index', ['status' => 'shortage'], false),
                ],
                [
                    'label' => 'Productos por reabastecer',
                    'critical' => false,
                    'count' => InventoryStock::query()
                        ->whereColumn('quantity_on_hand', '>=', 'quantity_reserved')
                        ->whereRaw('(quantity_on_hand - quantity_reserved) <= reorder_point')
                        ->count(),
                    'url' => route('inventory.index', ['status' => 'low'], false),
                ],
            ],
            'runs' => DeliveryRun::query()
                ->whereNotIn('status', [DeliveryRunStatus::Settled, DeliveryRunStatus::Cancelled])
                ->withCount([
                    'runOrders as stops_count',
                    'runOrders as completed_stops_count' => fn (Builder $query) => $query->whereIn('status', $completedStatuses),
                ])
                ->orderByRaw('case status when ? then 0 when ? then 1 when ? then 2 when ? then 3 else 4 end', [
                    DeliveryRunStatus::InTransit->value,
                    DeliveryRunStatus::AwaitingSettlement->value,
                    DeliveryRunStatus::Loaded->value,
                    DeliveryRunStatus::Preparing->value,
                ])
                ->orderBy('scheduled_date')
                ->orderBy('id')
                ->limit(8)
                ->get(),
            'trend' => $this->salesTrend($today->subDays(6), $tomorrow),
            'unassignedOrders' => Order::query()
                ->where('status', OrderStatus::Confirmed)
                ->oldest('confirmed_at')
                ->orderBy('id')
                ->limit(6)
                ->get(),
            'overdueReceivables' => (clone $overdue)
                ->with('customer:id,business_name')
                ->oldest('due_date')
                ->orderBy('id')
                ->limit(6)
                ->get(),
        ];
    }

    /** @return array{labels: list<string>, sold: list<float>, collected: list<float>} */
    private function salesTrend(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $sold = DeliveryRunOrder::query()
            ->where('completed_at', '>=', $start)
            ->where('completed_at', '<', $end)
            ->selectRaw('date(completed_at) as day, sum(delivered_total) as total')
            ->groupBy('day')
            ->pluck('total', 'day');
        $collectedOnDelivery = $this->activeDeliveryPayments()
            ->where('received_at', '>=', $start)
            ->where('received_at', '<', $end)
            ->selectRaw('date(received_at) as day, sum(amount) as total')
            ->groupBy('day')
            ->pluck('total', 'day');
        $collectedOnAccount = $this->activeCustomerPayments()
            ->where('occurred_at', '>=', $start)
            ->where('occurred_at', '<', $end)
            ->selectRaw('date(occurred_at) as day, sum(credit_amount) as total')
            ->groupBy('day')
            ->pluck('total', 'day');
        $trend = ['labels' => [], 'sold' => [], 'collected' => []];

        for ($date = $start; $date->lessThan($end); $date = $date->addDay()) {
            $day = $date->toDateString();
            $trend['labels'][] = $date->translatedFormat('D d');
            $trend['sold'][] = (float) ($sold[$day] ?? 0);
            $trend['collected'][] = (float) ($collectedOnDelivery[$day] ?? 0) + (float) ($collectedOnAccount[$day] ?? 0);
        }

        return $trend;
    }

    private function activeDeliveryPayments(): Builder
    {
        return DeliveryPayment::query()->where('status', DeliveryPaymentStatus::Active);
    }

    private function activeCustomerPayments(): Builder
    {
        return CustomerAccountEntry::query()
            ->where('type', CustomerAccountEntryType::CustomerPayment)
            ->where('status', CustomerAccountEntryStatus::Active);
    }

    private function warehouseDashboard(): array
    {
        $confirmedOrders = Order::query()->where('status', OrderStatus::Confirmed)->count();
        $pendingPreparation = DeliveryRun::query()
            ->whereIn('status', [DeliveryRunStatus::Draft, DeliveryRunStatus::Preparing])
            ->whereHas('runOrders', fn (Builder $query) => $query->where('status', DeliveryOrderStatus::Pending))
            ->count();
        $readyToLoad = DeliveryRun::query()
            ->where('status', DeliveryRunStatus::Preparing)
            ->whereHas('runOrders')
            ->whereDoesntHave('runOrders', fn (Builder $query) => $query->where('status', '!=', DeliveryOrderStatus::Prepared))
            ->count();
        $shortages = $this->inventoryShortagesQuery()->count();
        $preparationRuns = DeliveryRun::query()
            ->whereIn('status', [DeliveryRunStatus::Draft, DeliveryRunStatus::Preparing])
            ->withCount([
                'runOrders',
                'runOrders as pending_orders_count' => fn (Builder $query) => $query->where('status', DeliveryOrderStatus::Pending),
                'runOrders as prepared_orders_count' => fn (Builder $query) => $query->where('status', DeliveryOrderStatus::Prepared),
            ])
            ->orderBy('scheduled_date')
            ->orderBy('id')
            ->limit(10)
            ->get()
            ->map(fn (DeliveryRun $run): array => [
                'title' => $run->run_number.' · '.$run->driver_name,
                'meta' => $run->scheduled_date->format('d/m/Y').' · '.$run->prepared_orders_count.' preparados de '.$run->run_orders_count,
                'badge' => $run->status->label(),
                'tone' => $run->pending_orders_count > 0 ? 'amber' : 'emerald',
                'url' => route('delivery-runs.show', $run, false),
                'action' => $run->status === DeliveryRunStatus::Preparing ? 'Continuar' : 'Abrir',
                'priority' => $run->pending_orders_count > 0 ? 'urgent' : 'routine',
            ])->all();
        $stockAlerts = $this->inventoryShortagesQuery()
            ->with(['product:id,sku,name', 'warehouse:id,name'])
            ->orderByRaw('(quantity_on_hand - quantity_reserved) asc')
            ->limit(10)
            ->get()
            ->map(fn (InventoryStock $stock): array => [
                'title' => $stock->product->name,
                'meta' => $stock->warehouse->name.' · '.$stock->product->sku.' · disponible '.number_format((float) $stock->availableQuantity(), 2),
                'badge' => 'Faltante',
                'tone' => 'red',
                'url' => route('inventory.index', ['search' => $stock->product->sku, 'status' => 'shortage'], false),
                'action' => 'Ver inventario',
                'priority' => 'urgent',
            ])->all();

        return [
            'eyebrow' => 'Centro de preparación',
            'title' => 'Lo que bodega debe sacar hoy',
            'description' => 'Los pedidos confirmados aparecen de inmediato; las jornadas muestran el avance real de preparación y carga.',
            'cards' => [
                $this->card('Pedidos nuevos', $confirmedOrders, 'Confirmados aún sin jornada', 'emerald', route('orders.index', ['status' => OrderStatus::Confirmed->value], false)),
                $this->card('Por preparar', $pendingPreparation, 'Jornadas con pedidos pendientes', 'amber', route('delivery-runs.index', absolute: false)),
                $this->card('Listas para cargar', $readyToLoad, 'Toda la preparación completada', 'sky', route('delivery-runs.index', absolute: false)),
                $this->card('Faltantes', $shortages, 'Pueden bloquear preparación', 'red', route('inventory.index', ['status' => 'shortage'], false)),
            ],
            'sections' => [
                $this->confirmedOrdersSection(),
                $this->section('preparation', 'Preparación de jornadas', 'Ordenadas por fecha de salida y avance.', 'No hay jornadas pendientes de preparación.', $preparationRuns),
                $this->section('stock', 'Faltantes de inventario', 'Existencia disponible negativa que requiere atención.', 'No hay faltantes de inventario.', $stockAlerts),
            ],
            'trend' => $this->orderTrend(Order::query()->where('status', '!=', OrderStatus::Draft->value)),
        ];
    }

    private function salesDashboard(User $user): array
    {
        $orders = Order::query()->where('salesperson_id', $user->id);
        $routes = SalesRoute::query()->where('salesperson_id', $user->id)->active()->count();
        $customers = Customer::query()
            ->whereHas('routeStops.salesRoute', fn (Builder $query) => $query->where('salesperson_id', $user->id))
            ->count();
        $drafts = (clone $orders)->where('status', OrderStatus::Draft)->count();
        $inProcessStatuses = [OrderStatus::Confirmed, OrderStatus::Assigned, OrderStatus::Loaded, OrderStatus::InTransit];
        $inProcess = (clone $orders)->whereIn('status', $inProcessStatuses)->count();
        $todayTotal = (string) (clone $orders)->whereDate('order_date', today())->sum('total');
        $openOrders = (clone $orders)
            ->whereIn('status', [OrderStatus::Draft, ...$inProcessStatuses])
            ->latest('order_date')
            ->orderByDesc('id')
            ->limit(12)
            ->get()
            ->map(fn (Order $order): array => [
                'title' => $order->order_number.' · '.$order->customer_name,
                'meta' => $order->order_date->format('d/m/Y').' · HNL '.number_format((float) $order->total, 2),
                'badge' => $order->status->label(),
                'tone' => $order->status === OrderStatus::Draft ? 'amber' : 'sky',
                'url' => route('orders.show', $order, false),
                'action' => $order->status === OrderStatus::Draft ? 'Continuar' : 'Seguir',
                'priority' => $order->status === OrderStatus::Draft ? 'urgent' : 'routine',
            ])->all();

        return [
            'eyebrow' => 'Panel de preventa',
            'title' => 'Tu cartera y tus pedidos en movimiento',
            'description' => 'Seguimiento de la actividad asignada a tus rutas, desde el borrador hasta el resultado de entrega.',
            'cards' => [
                $this->card('Venta de hoy', $todayTotal, 'Pedidos registrados hoy', 'emerald', route('orders.index', ['date_from' => today()->toDateString()], false), 'money'),
                $this->card('Borradores', $drafts, 'Pedidos que debes terminar', 'amber', route('orders.index', ['status' => OrderStatus::Draft->value], false)),
                $this->card('En proceso', $inProcess, 'Confirmados, asignados o en ruta', 'sky', route('orders.index', absolute: false)),
                $this->card('Clientes en ruta', $customers, $routes.' rutas activas', 'violet', route('routes.index', absolute: false)),
            ],
            'sections' => [
                $this->section('orders', 'Seguimiento de pedidos', 'Primero aparecen los borradores que necesitan una acción.', 'No tienes pedidos abiertos.', $openOrders),
            ],
            'trend' => $this->orderTrend($orders),
        ];
    }

    private function confirmedOrdersSection(): array
    {
        $orders = Order::query()
            ->where('status', OrderStatus::Confirmed)
            ->latest('confirmed_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (Order $order): array => [
                'title' => $order->order_number.' · '.$order->customer_name,
                'meta' => ($order->confirmed_at?->diffForHumans() ?? $order->order_date->format('d/m/Y')).' · '.$order->salesperson_name.' · HNL '.number_format((float) $order->total, 2),
                'badge' => 'Nuevo',
                'tone' => 'emerald',
                'url' => route('orders.show', $order, false),
                'action' => 'Abrir pedido',
                'priority' => 'urgent',
            ])->all();

        return $this->section(
            'orders',
            'Pedidos confirmados sin asignar',
            'Nuevos pedidos disponibles para organizar en una jornada.',
            'No hay pedidos nuevos esperando asignación.',
            $orders,
        );
    }

    private function inventoryShortagesQuery(): Builder
    {
        return InventoryStock::query()->whereColumn('quantity_on_hand', '<', 'quantity_reserved');
    }

    /** @return array{labels: list<string>, orders: list<int>, sales: list<float>} */
    private function orderTrend(Builder $query): array
    {
        $start = CarbonImmutable::today()->subDays(6);
        $rows = (clone $query)
            ->whereBetween('order_date', [$start->toDateString(), today()->toDateString()])
            ->selectRaw('order_date, count(*) as order_count, coalesce(sum(total), 0) as order_total')
            ->groupBy('order_date')
            ->get()
            ->keyBy(fn (Order $order): string => $order->order_date->toDateString());
        $labels = [];
        $orders = [];
        $sales = [];

        foreach (range(0, 6) as $offset) {
            $date = $start->addDays($offset);
            $row = $rows->get($date->toDateString());
            $labels[] = $date->translatedFormat('D d');
            $orders[] = (int) ($row?->order_count ?? 0);
            $sales[] = (float) ($row?->order_total ?? 0);
        }

        return compact('labels', 'orders', 'sales');
    }

    private function card(string $label, int|string $value, string $hint, string $tone, string $url, ?string $format = null): array
    {
        $card = compact('label', 'value', 'hint', 'tone', 'url');

        if ($format !== null) {
            $card['format'] = $format;
        }

        return $card;
    }

    /** @param list<array{title: string, meta: string, badge: string, tone: string, url: string, action: string, priority: string}> $items */
    private function section(string $key, string $title, string $description, string $empty, array $items): array
    {
        return compact('key', 'title', 'description', 'empty', 'items');
    }

    /**
     * @return array{
     *     runs: Collection<int, DeliveryRun>,
     *     currentRun: ?DeliveryRun,
     *     nextStop: mixed,
     *     metrics: array{stops: int, completed: int, pending: int, progress: int, delivered: string, collected: string, credit: string},
     *     achievements: list<array{label: string, description: string, earned: bool}>
     * }
     */
    public function forDriver(User $user): array
    {
        $completedStatuses = [
            DeliveryOrderStatus::Delivered->value,
            DeliveryOrderStatus::PartiallyDelivered->value,
            DeliveryOrderStatus::NotDelivered->value,
        ];
        $openStatuses = collect(DeliveryRunStatus::cases())
            ->filter(fn (DeliveryRunStatus $status): bool => $status->isOpen())
            ->map->value
            ->all();
        $runs = DeliveryRun::query()
            ->visibleTo($user)
            ->where(function ($query) use ($openStatuses): void {
                $query->whereIn('status', $openStatuses)
                    ->orWhere(function ($today): void {
                        $today->where('status', DeliveryRunStatus::Settled)
                            ->whereDate('scheduled_date', today());
                    });
            })
            ->withCount([
                'runOrders as stops_count',
                'runOrders as completed_stops_count' => fn ($query) => $query->whereIn('status', $completedStatuses),
                'runOrders as delivered_stops_count' => fn ($query) => $query->where('status', DeliveryOrderStatus::Delivered),
            ])
            ->orderByRaw('case status when ? then 0 when ? then 1 when ? then 2 when ? then 3 else 4 end', [
                DeliveryRunStatus::InTransit->value,
                DeliveryRunStatus::Loaded->value,
                DeliveryRunStatus::AwaitingSettlement->value,
                DeliveryRunStatus::Preparing->value,
            ])
            ->orderByDesc('scheduled_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get();
        $currentRun = $runs->first();
        $nextStop = $currentRun?->runOrders()
            ->whereNotIn('status', $completedStatuses)
            ->with('order:id,order_number,customer_name,customer_address')
            ->orderBy('visit_order')
            ->orderBy('id')
            ->first();
        $stops = (int) $runs->sum('stops_count');
        $completed = (int) $runs->sum('completed_stops_count');
        $pending = max($stops - $completed, 0);
        $delivered = $this->sumMoney($runs, 'delivered_total');
        $collected = $this->sumMoney($runs, 'collected_total');
        $credit = $this->sumMoney($runs, 'credit_total');
        $progress = $stops > 0 ? (int) round(($completed / $stops) * 100) : 0;

        return [
            'runs' => $runs,
            'currentRun' => $currentRun,
            'nextStop' => $nextStop,
            'metrics' => [
                'stops' => $stops,
                'completed' => $completed,
                'pending' => $pending,
                'progress' => $progress,
                'delivered' => $delivered,
                'collected' => $collected,
                'credit' => $credit,
            ],
            'achievements' => [
                ['label' => 'Primera entrega', 'description' => 'Completaste al menos una parada.', 'earned' => $completed >= 1],
                ['label' => 'Mitad de la ruta', 'description' => 'Alcanzaste el 50% de las paradas.', 'earned' => $stops > 0 && $completed * 2 >= $stops],
                ['label' => 'Cobranza al día', 'description' => 'No tienes saldo pendiente en las jornadas visibles.', 'earned' => $completed > 0 && bccomp($credit, '0', 4) === 0],
                ['label' => 'Ruta completada', 'description' => 'Terminaste todas las paradas asignadas.', 'earned' => $stops > 0 && $pending === 0],
            ],
        ];
    }

    /** @param Collection<int, DeliveryRun> $runs */
    private function sumMoney(Collection $runs, string $attribute): string
    {
        return $runs->reduce(
            fn (string $total, DeliveryRun $run): string => bcadd($total, (string) $run->getAttribute($attribute), 4),
            '0.0000',
        );
    }
}
