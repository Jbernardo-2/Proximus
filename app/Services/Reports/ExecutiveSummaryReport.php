<?php

namespace App\Services\Reports;

use App\CustomerAccountEntryStatus;
use App\CustomerAccountEntryType;
use App\DeliveryPaymentStatus;
use App\DeliveryRunStatus;
use App\Models\CustomerAccountEntry;
use App\Models\CustomerReceivable;
use App\Models\DeliveryPayment;
use App\Models\DeliveryRun;
use App\Models\InventoryStock;
use Carbon\CarbonImmutable;

class ExecutiveSummaryReport extends Report
{
    public const string COLOR_REQUESTED = '#8a56c2';

    public const string COLOR_DELIVERED = '#188365';

    public const string COLOR_COLLECTED = '#2f6fd0';

    public const string COLOR_CREDIT = '#d98a00';

    public const string COLOR_FAILED = '#c2410c';

    public function key(): string
    {
        return 'resumen-ejecutivo';
    }

    public function group(): string
    {
        return 'Dirección';
    }

    public function title(): string
    {
        return 'Resumen ejecutivo';
    }

    public function description(): string
    {
        return 'Venta solicitada, entregada, cobrada, a crédito y perdida, comparadas con el período anterior.';
    }

    public function summary(ReportFilters $filters): array
    {
        $current = $this->facts->totals($filters);
        $previous = $this->facts->totals($filters->previous());
        $collected = $this->collectedInPeriod($filters);
        $previousCollected = $this->collectedInPeriod($filters->previous());
        $overdue = CustomerReceivable::query()->overdue();
        $differences = DeliveryRun::query()
            ->where('status', DeliveryRunStatus::Settled)
            ->where('settled_at', '>=', $filters->from)
            ->where('settled_at', '<', $filters->to->addDay())
            ->where('cash_difference', '!=', 0);

        return [
            ['label' => 'Venta solicitada', 'value' => $current['requested'], 'previous' => $previous['requested'], 'format' => 'money', 'hint' => $current['orders'].' pedidos recibidos'],
            ['label' => 'Venta entregada', 'value' => $current['delivered'], 'previous' => $previous['delivered'], 'format' => 'money', 'hint' => $current['sold_orders'].' pedidos con entrega'],
            ['label' => 'Venta perdida', 'value' => $current['lost'], 'previous' => $previous['lost'], 'format' => 'money', 'lower_is_better' => true, 'hint' => 'Solicitado que no se entregó'],
            ['label' => 'En proceso', 'value' => $current['in_process'], 'format' => 'money', 'hint' => 'Pedidos aún sin resultado'],
            ['label' => 'Cobrado en el período', 'value' => $collected, 'previous' => $previousCollected, 'format' => 'money', 'hint' => 'Por fecha de cobro, con abonos'],
            ['label' => 'Crédito generado', 'value' => $current['credit'], 'previous' => $previous['credit'], 'format' => 'money', 'lower_is_better' => true, 'hint' => 'Entregado que quedó pendiente'],
            ['label' => 'Por cobrar hoy', 'value' => (float) CustomerReceivable::query()->outstanding()->sum('outstanding_amount'), 'format' => 'money', 'hint' => 'Saldo total de la cartera'],
            ['label' => 'Cartera vencida hoy', 'value' => (float) (clone $overdue)->sum('outstanding_amount'), 'format' => 'money', 'hint' => (clone $overdue)->distinct()->count('customer_id').' clientes'],
            ['label' => 'Ticket promedio', 'value' => $current['sold_orders'] > 0 ? $current['delivered'] / $current['sold_orders'] : 0, 'previous' => $previous['sold_orders'] > 0 ? $previous['delivered'] / $previous['sold_orders'] : 0, 'format' => 'money', 'hint' => 'Entregado por pedido'],
            ['label' => 'Clientes atendidos', 'value' => $current['customers'], 'previous' => $previous['customers'], 'format' => 'integer', 'hint' => 'Con al menos una entrega'],
            ['label' => 'Cumplimiento de pedidos', 'value' => self::ratio($current['delivered'], $current['delivered'] + $current['lost']) ?? 0, 'previous' => self::ratio($previous['delivered'], $previous['delivered'] + $previous['lost']), 'format' => 'percent', 'hint' => 'Entregado sobre lo solicitado ya cerrado'],
            ['label' => 'Entregas completas', 'value' => self::ratio($current['delivered_orders'], $current['closed_orders']) ?? 0, 'previous' => self::ratio($previous['delivered_orders'], $previous['closed_orders']), 'format' => 'percent', 'hint' => $current['delivered_orders'].' completas · '.$current['partial_orders'].' parciales · '.$current['failed_orders'].' fallidas'],
            ['label' => 'Pedidos cancelados', 'value' => $current['cancelled_orders'], 'previous' => $previous['cancelled_orders'], 'format' => 'integer', 'lower_is_better' => true, 'hint' => 'Valor '.number_format($current['cancelled'], 2)],
            ['label' => 'Diferencias de efectivo', 'value' => (float) (clone $differences)->sum('cash_difference'), 'format' => 'money', 'hint' => (clone $differences)->count().' jornadas liquidadas con diferencia'],
            ['label' => 'Jornadas por liquidar', 'value' => DeliveryRun::query()->where('status', DeliveryRunStatus::AwaitingSettlement)->count(), 'format' => 'integer', 'hint' => 'Pendientes hoy'],
            ['label' => 'Productos con faltante', 'value' => InventoryStock::query()->whereColumn('quantity_on_hand', '<', 'quantity_reserved')->count(), 'format' => 'integer', 'hint' => 'Existencia menor que lo reservado'],
        ];
    }

    public function charts(ReportFilters $filters): array
    {
        $totals = $this->facts->totals($filters);
        $trend = $this->trend($filters);
        $routes = $this->ranking($filters, 'o.sales_route_id', "coalesce(min(o.route_name), 'Fuera de ruta')");
        $salespeople = $this->ranking($filters, 'o.salesperson_id', 'min(o.salesperson_name)');
        $products = $this->facts->lines($filters)
            ->selectRaw('min(oi.product_name) as label, sum('.SalesFacts::LINE_DELIVERED.') as total')
            ->groupBy('oi.product_id')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->filter(fn (object $row): bool => (float) $row->total > 0);
        $outstanding = (float) CustomerReceivable::query()->outstanding()->sum('outstanding_amount');
        $overdue = (float) CustomerReceivable::query()->overdue()->sum('outstanding_amount');

        return [
            [
                'title' => 'Venta entregada y cobros',
                'type' => 'columns',
                'format' => 'money',
                'labels' => $trend['labels'],
                'series' => [
                    ['name' => 'Entregado', 'color' => self::COLOR_DELIVERED, 'values' => $trend['delivered']],
                    ['name' => 'Cobrado', 'color' => self::COLOR_COLLECTED, 'values' => $trend['collected']],
                ],
            ],
            [
                'title' => 'Solicitado contra entregado',
                'type' => 'columns',
                'format' => 'money',
                'labels' => $trend['labels'],
                'series' => [
                    ['name' => 'Solicitado', 'color' => self::COLOR_REQUESTED, 'values' => $trend['requested']],
                    ['name' => 'Entregado', 'color' => self::COLOR_DELIVERED, 'values' => $trend['delivered']],
                ],
            ],
            $this->parts('Contado contra crédito', 'money', [
                ['Contado', self::COLOR_DELIVERED, $totals['delivered'] - $totals['credit']],
                ['Crédito', self::COLOR_CREDIT, $totals['credit']],
            ]),
            $this->parts('Resultado de las entregas', 'integer', [
                ['Completas', self::COLOR_DELIVERED, $totals['delivered_orders']],
                ['Parciales', self::COLOR_CREDIT, $totals['partial_orders']],
                ['Fallidas', self::COLOR_FAILED, $totals['failed_orders']],
            ]),
            $this->bars('Venta entregada por ruta', $routes->pluck('label')->all(), $routes->pluck('total')->map(fn ($value): float => (float) $value)->all()),
            $this->bars('Venta entregada por preventista', $salespeople->pluck('label')->all(), $salespeople->pluck('total')->map(fn ($value): float => (float) $value)->all()),
            $this->bars('Productos más vendidos', $products->pluck('label')->all(), $products->pluck('total')->map(fn ($value): float => (float) $value)->all()),
            $this->parts('Cartera vigente y vencida', 'money', [
                ['Vigente', self::COLOR_COLLECTED, $outstanding - $overdue],
                ['Vencida', self::COLOR_FAILED, $overdue],
            ]),
        ];
    }

    private function collectedInPeriod(ReportFilters $filters): float
    {
        $end = $filters->to->addDay();
        $onDelivery = DeliveryPayment::query()
            ->where('status', DeliveryPaymentStatus::Active)
            ->where('received_at', '>=', $filters->from)
            ->where('received_at', '<', $end)
            ->sum('amount');
        $onAccount = CustomerAccountEntry::query()
            ->where('type', CustomerAccountEntryType::CustomerPayment)
            ->where('status', CustomerAccountEntryStatus::Active)
            ->where('occurred_at', '>=', $filters->from)
            ->where('occurred_at', '<', $end)
            ->sum('credit_amount');

        return (float) $onDelivery + (float) $onAccount;
    }

    /**
     * @return array{labels: list<string>, requested: list<float>, delivered: list<float>, collected: list<float>}
     */
    private function trend(ReportFilters $filters): array
    {
        $day = $filters->basis === 'delivery' ? 'date(ro.completed_at)' : 'o.order_date';
        $sales = $this->facts->ordersWithProductFilters($filters)
            ->selectRaw("{$day} as day, sum(".SalesFacts::REQUESTED.') as requested, sum('.SalesFacts::DELIVERED.') as delivered')
            ->groupByRaw($day)
            ->get()
            ->keyBy(fn (object $row): string => substr((string) $row->day, 0, 10));
        $end = $filters->to->addDay();
        $collected = [];

        foreach ([
            DeliveryPayment::query()
                ->where('status', DeliveryPaymentStatus::Active)
                ->where('received_at', '>=', $filters->from)
                ->where('received_at', '<', $end)
                ->selectRaw('date(received_at) as day, sum(amount) as total'),
            CustomerAccountEntry::query()
                ->where('type', CustomerAccountEntryType::CustomerPayment)
                ->where('status', CustomerAccountEntryStatus::Active)
                ->where('occurred_at', '>=', $filters->from)
                ->where('occurred_at', '<', $end)
                ->selectRaw('date(occurred_at) as day, sum(credit_amount) as total'),
        ] as $payments) {
            foreach ($payments->groupBy('day')->toBase()->get() as $row) {
                $collected[$row->day] = ($collected[$row->day] ?? 0) + (float) $row->total;
            }
        }

        $bucket = match (true) {
            $filters->days() <= 31 => fn (CarbonImmutable $date): array => [$date->toDateString(), $date->format('d/m')],
            $filters->days() <= 182 => fn (CarbonImmutable $date): array => [$date->startOfWeek()->toDateString(), 'Sem. '.$date->startOfWeek()->format('d/m')],
            default => fn (CarbonImmutable $date): array => [$date->format('Y-m'), ucfirst($date->translatedFormat('M Y'))],
        };
        $buckets = [];

        for ($date = $filters->from; $date->lessThanOrEqualTo($filters->to); $date = $date->addDay()) {
            [$key, $label] = $bucket($date);
            $buckets[$key] ??= ['label' => $label, 'requested' => 0.0, 'delivered' => 0.0, 'collected' => 0.0];
            $row = $sales->get($date->toDateString());
            $buckets[$key]['requested'] += (float) ($row->requested ?? 0);
            $buckets[$key]['delivered'] += (float) ($row->delivered ?? 0);
            $buckets[$key]['collected'] += $collected[$date->toDateString()] ?? 0;
        }

        return [
            'labels' => array_column($buckets, 'label'),
            'requested' => array_column($buckets, 'requested'),
            'delivered' => array_column($buckets, 'delivered'),
            'collected' => array_column($buckets, 'collected'),
        ];
    }

    private function ranking(ReportFilters $filters, string $groupBy, string $label)
    {
        return $this->facts->ordersWithProductFilters($filters)
            ->selectRaw("{$label} as label, sum(".SalesFacts::DELIVERED.') as total')
            ->groupBy($groupBy)
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->filter(fn (object $row): bool => (float) $row->total > 0);
    }

    /**
     * @param  list<string>  $labels
     * @param  list<float>  $values
     */
    private function bars(string $title, array $labels, array $values): array
    {
        return [
            'title' => $title,
            'type' => 'bars',
            'format' => 'money',
            'labels' => $labels,
            'series' => [['name' => 'Entregado', 'color' => self::COLOR_DELIVERED, 'values' => $values]],
        ];
    }

    /** @param list<array{0: string, 1: string, 2: float|int}> $parts */
    private function parts(string $title, string $format, array $parts): array
    {
        return [
            'title' => $title,
            'type' => 'parts',
            'format' => $format,
            'labels' => array_column($parts, 0),
            'series' => array_map(fn (array $part): array => ['name' => $part[0], 'color' => $part[1], 'values' => [$part[2]]], $parts),
        ];
    }
}
