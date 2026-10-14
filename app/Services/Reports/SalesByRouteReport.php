<?php

namespace App\Services\Reports;

use Illuminate\Database\Query\Builder;

class SalesByRouteReport extends Report
{
    public function key(): string
    {
        return 'ventas-ruta';
    }

    public function group(): string
    {
        return 'Ventas';
    }

    public function title(): string
    {
        return 'Ventas por ruta';
    }

    public function description(): string
    {
        return 'Clientes asignados y con pedido, venta solicitada y entregada, cumplimiento, resultado de entregas, cobrado y crédito por ruta.';
    }

    public function dimensions(): array
    {
        return ['salesperson', 'driver', 'payment_term', 'category', 'brand', 'warehouse'];
    }

    public function charts(ReportFilters $filters): array
    {
        $rows = $this->query($filters)->orderByDesc('delivered')->limit(10)->get();

        return [[
            'title' => 'Solicitado contra entregado por ruta',
            'type' => 'columns',
            'format' => 'money',
            'labels' => $rows->map(fn (object $row): string => $row->route_name ?: 'Fuera de ruta')->all(),
            'series' => [
                ['name' => 'Solicitado', 'color' => ExecutiveSummaryReport::COLOR_REQUESTED, 'values' => $rows->map(fn (object $row): float => (float) $row->requested)->all()],
                ['name' => 'Entregado', 'color' => ExecutiveSummaryReport::COLOR_DELIVERED, 'values' => $rows->map(fn (object $row): float => (float) $row->delivered)->all()],
            ],
        ]];
    }

    public function columns(): array
    {
        return [
            ['key' => 'route_name', 'label' => 'Ruta', 'format' => 'text', 'sortable' => true],
            ['key' => 'assigned_customers', 'label' => 'Clientes asignados', 'format' => 'integer', 'sortable' => true],
            ['key' => 'customers', 'label' => 'Clientes con pedido', 'format' => 'integer', 'sortable' => true],
            ['key' => 'coverage', 'label' => 'Cobertura', 'format' => 'percent'],
            ['key' => 'orders', 'label' => 'Pedidos', 'format' => 'integer', 'sortable' => true, 'total' => true],
            ['key' => 'requested', 'label' => 'Solicitado', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'delivered', 'label' => 'Entregado', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'average_per_customer', 'label' => 'Promedio por cliente', 'format' => 'money'],
            ['key' => 'fulfillment', 'label' => 'Cumplimiento', 'format' => 'percent'],
            ['key' => 'delivered_orders', 'label' => 'Completas', 'format' => 'integer', 'sortable' => true, 'total' => true],
            ['key' => 'partial_orders', 'label' => 'Parciales', 'format' => 'integer', 'sortable' => true, 'total' => true],
            ['key' => 'failed_orders', 'label' => 'Fallidas', 'format' => 'integer', 'sortable' => true, 'total' => true],
            ['key' => 'collected', 'label' => 'Cobrado', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'credit', 'label' => 'Crédito', 'format' => 'money', 'sortable' => true, 'total' => true],
        ];
    }

    public function defaultSort(): array
    {
        return ['delivered', 'desc'];
    }

    public function query(ReportFilters $filters): ?Builder
    {
        return $this->facts->ordersWithProductFilters($filters)
            ->where('o.status', '!=', 'cancelled')
            ->groupBy('o.sales_route_id')
            ->selectRaw(implode(', ', [
                'o.sales_route_id',
                'min(o.route_name) as route_name',
                '(select count(*) from route_stops stops where stops.sales_route_id = o.sales_route_id and stops.is_active = 1) as assigned_customers',
                'count(distinct o.customer_id) as customers',
                'count(*) as orders',
                'sum('.SalesFacts::REQUESTED.') as requested',
                'sum('.SalesFacts::DELIVERED.') as delivered',
                'sum('.SalesFacts::LOST.') as lost',
                "sum(case when o.status = 'delivered' then 1 else 0 end) as delivered_orders",
                "sum(case when o.status = 'partially_delivered' then 1 else 0 end) as partial_orders",
                "sum(case when o.status = 'not_delivered' then 1 else 0 end) as failed_orders",
                'sum('.SalesFacts::COLLECTED.') as collected',
                'sum('.SalesFacts::CREDIT.') as credit',
            ]));
    }

    public function mapRow(object $row): array
    {
        return [
            ...(array) $row,
            'route_name' => $row->route_name ?: 'Fuera de ruta',
            'coverage' => $row->sales_route_id === null ? null : self::ratio((int) $row->customers, (int) $row->assigned_customers),
            'average_per_customer' => (int) $row->customers > 0 ? (float) $row->delivered / (int) $row->customers : null,
            'fulfillment' => self::ratio((float) $row->delivered, (float) $row->delivered + (float) $row->lost),
            '_url' => $row->sales_route_id === null ? null : route('routes.show', $row->sales_route_id, false),
        ];
    }
}
