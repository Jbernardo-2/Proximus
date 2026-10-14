<?php

namespace App\Services\Reports;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Single definition of the commercial figures every sales report shares, so
 * "requested", "delivered", "collected", "credit" and "lost" mean the same everywhere.
 *
 * Each order is measured against its latest delivery attempt: earlier attempts
 * of a requeued order ended as not delivered and carry no sale.
 */
class SalesFacts
{
    public const string CLOSED = "o.status in ('delivered', 'partially_delivered', 'not_delivered')";

    public const string REQUESTED = "case when o.status <> 'cancelled' then o.total else 0 end";

    public const string DELIVERED = 'case when '.self::CLOSED.' then coalesce(ro.delivered_total, 0) else 0 end';

    public const string LOST = 'case when '.self::CLOSED.' then o.total - coalesce(ro.delivered_total, 0) else 0 end';

    public const string IN_PROCESS = "case when o.status in ('confirmed', 'assigned', 'loaded', 'in_transit') then o.total else 0 end";

    public const string CANCELLED = "case when o.status = 'cancelled' then o.total else 0 end";

    public const string COLLECTED = 'case when '.self::CLOSED.' then coalesce(ro.collected_total, 0) else 0 end';

    public const string CREDIT = 'case when '.self::CLOSED.' then coalesce(ro.balance_due, 0) else 0 end';

    public const string LINE_REQUESTED_QUANTITY = "case when o.status <> 'cancelled' then oi.quantity else 0 end";

    public const string LINE_REQUESTED = "case when o.status <> 'cancelled' then oi.line_total else 0 end";

    public const string LINE_PREPARED = 'coalesce(ri.prepared_quantity, 0) * oi.unit_price';

    public const string LINE_LOADED = 'coalesce(ri.loaded_quantity, 0) * oi.unit_price';

    public const string LINE_DELIVERED_QUANTITY = 'case when '.self::CLOSED.' then coalesce(ri.delivered_quantity, 0) else 0 end';

    public const string LINE_DELIVERED = 'case when '.self::CLOSED.' then coalesce(ri.delivered_line_total, 0) else 0 end';

    /** One row per order that left draft, joined to its latest delivery attempt and run. */
    public function orders(ReportFilters $filters): Builder
    {
        $query = DB::table('orders as o')
            ->leftJoin('delivery_run_orders as ro', function ($join): void {
                $join->whereRaw('ro.id = (select max(attempt.id) from delivery_run_orders attempt where attempt.order_id = o.id)');
            })
            ->leftJoin('delivery_runs as r', 'r.id', '=', 'ro.delivery_run_id')
            ->where('o.status', '!=', 'draft');

        if ($filters->basis === 'delivery') {
            $query->whereRaw(self::CLOSED)
                ->where('ro.completed_at', '>=', $filters->from)
                ->where('ro.completed_at', '<', $filters->to->addDay());
        } else {
            $query->where('o.order_date', '>=', $filters->from->toDateString())
                ->where('o.order_date', '<', $filters->to->addDay()->toDateString());
        }

        foreach ([
            'route' => 'o.sales_route_id',
            'salesperson' => 'o.salesperson_id',
            'driver' => 'r.driver_id',
            'customer' => 'o.customer_id',
            'payment_term' => 'o.payment_term',
            'status' => 'o.status',
            'warehouse' => 'o.warehouse_id',
        ] as $dimension => $column) {
            if ($filters->get($dimension) !== null) {
                $query->where($column, $filters->get($dimension));
            }
        }

        return $query;
    }

    /** Orders narrowed to those that include a product of the filtered category or brand. */
    public function ordersWithProductFilters(ReportFilters $filters): Builder
    {
        $query = $this->orders($filters);

        if ($filters->get('category') === null && $filters->get('brand') === null) {
            return $query;
        }

        return $query->whereExists(function (Builder $lines) use ($filters): void {
            $lines->selectRaw('1')
                ->from('order_items as filtered_items')
                ->join('products as filtered_products', 'filtered_products.id', '=', 'filtered_items.product_id')
                ->whereColumn('filtered_items.order_id', 'o.id')
                ->when($filters->get('category') !== null, fn (Builder $q) => $q->where('filtered_products.category_id', $filters->get('category')))
                ->when($filters->get('brand') !== null, fn (Builder $q) => $q->where('filtered_products.brand_id', $filters->get('brand')));
        });
    }

    /** One row per ordered line, joined to what happened to it on its latest delivery attempt. */
    public function lines(ReportFilters $filters): Builder
    {
        return $this->orders($filters)
            ->join('order_items as oi', 'oi.order_id', '=', 'o.id')
            ->join('products as p', 'p.id', '=', 'oi.product_id')
            ->leftJoin('delivery_run_items as ri', function ($join): void {
                $join->on('ri.order_item_id', '=', 'oi.id')->on('ri.delivery_run_order_id', '=', 'ro.id');
            })
            ->when($filters->get('category') !== null, fn (Builder $q) => $q->where('p.category_id', $filters->get('category')))
            ->when($filters->get('brand') !== null, fn (Builder $q) => $q->where('p.brand_id', $filters->get('brand')));
    }

    /**
     * @return array{requested: float, delivered: float, lost: float, in_process: float, cancelled: float, collected: float, credit: float, orders: int, delivered_orders: int, partial_orders: int, failed_orders: int, cancelled_orders: int, closed_orders: int, sold_orders: int, customers: int}
     */
    public function totals(ReportFilters $filters): array
    {
        $row = $this->ordersWithProductFilters($filters)->selectRaw(implode(', ', [
            'coalesce(sum('.self::REQUESTED.'), 0) as requested',
            'coalesce(sum('.self::DELIVERED.'), 0) as delivered',
            'coalesce(sum('.self::LOST.'), 0) as lost',
            'coalesce(sum('.self::IN_PROCESS.'), 0) as in_process',
            'coalesce(sum('.self::CANCELLED.'), 0) as cancelled',
            'coalesce(sum('.self::COLLECTED.'), 0) as collected',
            'coalesce(sum('.self::CREDIT.'), 0) as credit',
            "coalesce(sum(case when o.status <> 'cancelled' then 1 else 0 end), 0) as orders",
            "coalesce(sum(case when o.status = 'delivered' then 1 else 0 end), 0) as delivered_orders",
            "coalesce(sum(case when o.status = 'partially_delivered' then 1 else 0 end), 0) as partial_orders",
            "coalesce(sum(case when o.status = 'not_delivered' then 1 else 0 end), 0) as failed_orders",
            "coalesce(sum(case when o.status = 'cancelled' then 1 else 0 end), 0) as cancelled_orders",
            'coalesce(sum(case when '.self::CLOSED.' then 1 else 0 end), 0) as closed_orders',
            "coalesce(sum(case when o.status in ('delivered', 'partially_delivered') then 1 else 0 end), 0) as sold_orders",
            "count(distinct case when o.status in ('delivered', 'partially_delivered') then o.customer_id end) as customers",
        ]))->first();

        return [
            'requested' => (float) $row->requested,
            'delivered' => (float) $row->delivered,
            'lost' => (float) $row->lost,
            'in_process' => (float) $row->in_process,
            'cancelled' => (float) $row->cancelled,
            'collected' => (float) $row->collected,
            'credit' => (float) $row->credit,
            'orders' => (int) $row->orders,
            'delivered_orders' => (int) $row->delivered_orders,
            'partial_orders' => (int) $row->partial_orders,
            'failed_orders' => (int) $row->failed_orders,
            'cancelled_orders' => (int) $row->cancelled_orders,
            'closed_orders' => (int) $row->closed_orders,
            'sold_orders' => (int) $row->sold_orders,
            'customers' => (int) $row->customers,
        ];
    }
}
