<?php

namespace App\Services\Reports;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Delivery runs that actually left planning, with their stops and merchandise summarised.
 * The period filters on the scheduled date of the run.
 */
class DeliveryFacts
{
    public const string COMPLETED = "('delivered', 'partially_delivered', 'not_delivered')";

    public function runs(ReportFilters $filters): Builder
    {
        $stops = DB::table('delivery_run_orders')
            ->groupBy('delivery_run_id')
            ->selectRaw(implode(', ', [
                'delivery_run_id',
                'count(*) as stops',
                'sum(case when status in '.self::COMPLETED.' then 1 else 0 end) as completed_stops',
                "sum(case when status = 'delivered' then 1 else 0 end) as delivered_stops",
                "sum(case when status = 'partially_delivered' then 1 else 0 end) as partial_stops",
                "sum(case when status = 'not_delivered' then 1 else 0 end) as failed_stops",
            ]));
        $goods = DB::table('delivery_run_items as goods_items')
            ->join('delivery_run_orders as goods_orders', 'goods_orders.id', '=', 'goods_items.delivery_run_order_id')
            ->groupBy('goods_orders.delivery_run_id')
            ->selectRaw(implode(', ', [
                'goods_orders.delivery_run_id',
                'sum(goods_items.loaded_quantity * goods_items.unit_price) as loaded_value',
                'sum(goods_items.returned_quantity * goods_items.unit_price) as returned_value',
                'sum(goods_items.damaged_quantity * goods_items.unit_price) as damaged_value',
                'sum(goods_items.missing_quantity * goods_items.unit_price) as missing_value',
            ]));

        return DB::table('delivery_runs as r')
            ->leftJoinSub($stops, 's', 's.delivery_run_id', '=', 'r.id')
            ->leftJoinSub($goods, 'g', 'g.delivery_run_id', '=', 'r.id')
            ->whereNotIn('r.status', ['draft', 'cancelled'])
            ->where('r.scheduled_date', '>=', $filters->from->toDateString())
            ->where('r.scheduled_date', '<', $filters->to->addDay()->toDateString())
            ->when($filters->get('driver') !== null, fn (Builder $query) => $query->where('r.driver_id', $filters->get('driver')))
            ->when($filters->get('vehicle') !== null, fn (Builder $query) => $query->where('r.vehicle_id', $filters->get('vehicle')))
            ->when($filters->get('warehouse') !== null, fn (Builder $query) => $query->where('r.warehouse_id', $filters->get('warehouse')));
    }

    /**
     * @return array{runs: int, stops: int, completed_stops: int, delivered_stops: int, partial_stops: int, failed_stops: int, loaded: float, delivered: float, collected: float, credit: float, returned: float, damaged: float, missing: float}
     */
    public function totals(ReportFilters $filters): array
    {
        $row = $this->runs($filters)->selectRaw(implode(', ', [
            'count(*) as runs',
            'coalesce(sum(s.stops), 0) as stops',
            'coalesce(sum(s.completed_stops), 0) as completed_stops',
            'coalesce(sum(s.delivered_stops), 0) as delivered_stops',
            'coalesce(sum(s.partial_stops), 0) as partial_stops',
            'coalesce(sum(s.failed_stops), 0) as failed_stops',
            'coalesce(sum(g.loaded_value), 0) as loaded',
            'coalesce(sum(r.delivered_total), 0) as delivered',
            'coalesce(sum(r.collected_total), 0) as collected',
            'coalesce(sum(r.credit_total), 0) as credit',
            'coalesce(sum(g.returned_value), 0) as returned',
            'coalesce(sum(g.damaged_value), 0) as damaged',
            'coalesce(sum(g.missing_value), 0) as missing',
        ]))->first();

        return [
            'runs' => (int) $row->runs,
            'stops' => (int) $row->stops,
            'completed_stops' => (int) $row->completed_stops,
            'delivered_stops' => (int) $row->delivered_stops,
            'partial_stops' => (int) $row->partial_stops,
            'failed_stops' => (int) $row->failed_stops,
            'loaded' => (float) $row->loaded,
            'delivered' => (float) $row->delivered,
            'collected' => (float) $row->collected,
            'credit' => (float) $row->credit,
            'returned' => (float) $row->returned,
            'damaged' => (float) $row->damaged,
            'missing' => (float) $row->missing,
        ];
    }
}
