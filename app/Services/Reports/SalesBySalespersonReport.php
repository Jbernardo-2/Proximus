<?php

namespace App\Services\Reports;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class SalesBySalespersonReport extends Report
{
    public function key(): string
    {
        return 'ventas-preventista';
    }

    public function group(): string
    {
        return 'Ventas';
    }

    public function title(): string
    {
        return 'Ventas por preventista';
    }

    public function description(): string
    {
        return 'Pedidos tomados, venta solicitada y finalmente entregada, clientes, cumplimiento, cancelaciones y precios modificados.';
    }

    public function dimensions(): array
    {
        return ['route', 'driver', 'payment_term', 'category', 'brand', 'warehouse'];
    }

    public function charts(ReportFilters $filters): array
    {
        $rows = $this->query($filters)->orderByDesc('delivered')->limit(10)->get();

        return [[
            'title' => 'Solicitado contra entregado por preventista',
            'type' => 'columns',
            'format' => 'money',
            'labels' => $rows->pluck('salesperson_name')->all(),
            'series' => [
                ['name' => 'Solicitado', 'color' => ExecutiveSummaryReport::COLOR_REQUESTED, 'values' => $rows->map(fn (object $row): float => (float) $row->requested)->all()],
                ['name' => 'Entregado', 'color' => ExecutiveSummaryReport::COLOR_DELIVERED, 'values' => $rows->map(fn (object $row): float => (float) $row->delivered)->all()],
            ],
        ]];
    }

    public function columns(): array
    {
        return [
            ['key' => 'salesperson_name', 'label' => 'Preventista', 'format' => 'text', 'sortable' => true],
            ['key' => 'orders', 'label' => 'Pedidos tomados', 'format' => 'integer', 'sortable' => true, 'total' => true],
            ['key' => 'requested', 'label' => 'Solicitado', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'delivered', 'label' => 'Entregado', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'customers', 'label' => 'Clientes', 'format' => 'integer', 'sortable' => true],
            ['key' => 'average_ticket', 'label' => 'Ticket promedio', 'format' => 'money'],
            ['key' => 'fulfillment', 'label' => 'Cumplimiento', 'format' => 'percent'],
            ['key' => 'cancelled_orders', 'label' => 'Cancelados', 'format' => 'integer', 'sortable' => true, 'total' => true],
            ['key' => 'price_overrides', 'label' => 'Precios modificados', 'format' => 'integer', 'sortable' => true, 'total' => true],
            ['key' => 'cash', 'label' => 'Contado', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'credit', 'label' => 'Crédito', 'format' => 'money', 'sortable' => true, 'total' => true],
        ];
    }

    public function defaultSort(): array
    {
        return ['delivered', 'desc'];
    }

    public function query(ReportFilters $filters): ?Builder
    {
        $overrides = DB::table('order_items')
            ->whereNotNull('price_overridden_by')
            ->groupBy('order_id')
            ->selectRaw('order_id, count(*) as total');

        return $this->facts->ordersWithProductFilters($filters)
            ->leftJoinSub($overrides, 'overrides', 'overrides.order_id', '=', 'o.id')
            ->groupBy('o.salesperson_id')
            ->selectRaw(implode(', ', [
                'o.salesperson_id',
                'min(o.salesperson_name) as salesperson_name',
                "sum(case when o.status <> 'cancelled' then 1 else 0 end) as orders",
                'sum('.SalesFacts::REQUESTED.') as requested',
                'sum('.SalesFacts::DELIVERED.') as delivered',
                'sum('.SalesFacts::LOST.') as lost',
                "count(distinct case when o.status <> 'cancelled' then o.customer_id end) as customers",
                "sum(case when o.status in ('delivered', 'partially_delivered') then 1 else 0 end) as sold_orders",
                "sum(case when o.status = 'cancelled' then 1 else 0 end) as cancelled_orders",
                'sum(coalesce(overrides.total, 0)) as price_overrides',
                'sum('.SalesFacts::DELIVERED.') - sum('.SalesFacts::CREDIT.') as cash',
                'sum('.SalesFacts::CREDIT.') as credit',
            ]));
    }

    public function mapRow(object $row): array
    {
        return [
            ...(array) $row,
            'average_ticket' => (int) $row->sold_orders > 0 ? (float) $row->delivered / (int) $row->sold_orders : null,
            'fulfillment' => self::ratio((float) $row->delivered, (float) $row->delivered + (float) $row->lost),
        ];
    }
}
