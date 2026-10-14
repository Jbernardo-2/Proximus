<?php

namespace App\Services\Reports;

use Illuminate\Database\Query\Builder;

class SalesByCustomerReport extends Report
{
    public function key(): string
    {
        return 'ventas-cliente';
    }

    public function group(): string
    {
        return 'Ventas';
    }

    public function title(): string
    {
        return 'Ventas por cliente';
    }

    public function description(): string
    {
        return 'Compras, ticket promedio, última compra, contado y crédito, entregas fallidas y saldo actual de cada cliente.';
    }

    public function searchPlaceholder(): ?string
    {
        return 'Cliente o código';
    }

    public function summary(ReportFilters $filters): array
    {
        $current = $this->facts->totals($filters);
        $previous = $this->facts->totals($filters->previous());

        return [
            ['label' => 'Clientes con entrega', 'value' => $current['customers'], 'previous' => $previous['customers'], 'format' => 'integer'],
            ['label' => 'Venta entregada', 'value' => $current['delivered'], 'previous' => $previous['delivered'], 'format' => 'money'],
            ['label' => 'Promedio por cliente', 'value' => $current['customers'] > 0 ? $current['delivered'] / $current['customers'] : 0, 'previous' => $previous['customers'] > 0 ? $previous['delivered'] / $previous['customers'] : 0, 'format' => 'money'],
            ['label' => 'Venta a crédito', 'value' => $current['credit'], 'previous' => $previous['credit'], 'format' => 'money', 'lower_is_better' => true],
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'customer_name', 'label' => 'Cliente', 'format' => 'text', 'sortable' => true],
            ['key' => 'customer_code', 'label' => 'Código', 'format' => 'text', 'sortable' => true],
            ['key' => 'route_name', 'label' => 'Ruta', 'format' => 'text', 'sortable' => true],
            ['key' => 'salesperson_name', 'label' => 'Preventista', 'format' => 'text', 'sortable' => true],
            ['key' => 'orders', 'label' => 'Pedidos', 'format' => 'integer', 'sortable' => true, 'total' => true],
            ['key' => 'requested', 'label' => 'Solicitado', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'delivered', 'label' => 'Entregado', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'average_ticket', 'label' => 'Ticket promedio', 'format' => 'money'],
            ['key' => 'last_order_date', 'label' => 'Última compra', 'format' => 'date', 'sortable' => true],
            ['key' => 'cash', 'label' => 'Contado', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'credit', 'label' => 'Crédito', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'incomplete_orders', 'label' => 'Parciales o fallidas', 'format' => 'integer', 'sortable' => true, 'total' => true],
            ['key' => 'outstanding', 'label' => 'Saldo pendiente', 'format' => 'money', 'sortable' => true],
            ['key' => 'overdue', 'label' => 'Saldo vencido', 'format' => 'money', 'sortable' => true],
        ];
    }

    public function defaultSort(): array
    {
        return ['delivered', 'desc'];
    }

    public function query(ReportFilters $filters): ?Builder
    {
        $open = "receivables.status in ('open', 'partially_paid')";

        return $this->facts->ordersWithProductFilters($filters)
            ->where('o.status', '!=', 'cancelled')
            ->when($filters->search !== '', fn (Builder $query) => $query->where(function (Builder $match) use ($filters): void {
                $match->where('o.customer_name', 'like', "%{$filters->search}%")
                    ->orWhere('o.customer_code', 'like', "%{$filters->search}%");
            }))
            ->groupBy('o.customer_id')
            ->selectRaw(implode(', ', [
                'o.customer_id',
                'min(o.customer_name) as customer_name',
                'min(o.customer_code) as customer_code',
                'min(o.route_name) as route_name',
                'min(o.salesperson_name) as salesperson_name',
                'count(*) as orders',
                'sum('.SalesFacts::REQUESTED.') as requested',
                'sum('.SalesFacts::DELIVERED.') as delivered',
                "sum(case when o.status in ('delivered', 'partially_delivered') then 1 else 0 end) as sold_orders",
                'max(o.order_date) as last_order_date',
                'sum('.SalesFacts::DELIVERED.') - sum('.SalesFacts::CREDIT.') as cash',
                'sum('.SalesFacts::CREDIT.') as credit',
                "sum(case when o.status in ('partially_delivered', 'not_delivered') then 1 else 0 end) as incomplete_orders",
                "(select coalesce(sum(receivables.outstanding_amount), 0) from customer_receivables receivables where receivables.customer_id = o.customer_id and {$open}) as outstanding",
                "(select coalesce(sum(receivables.outstanding_amount), 0) from customer_receivables receivables where receivables.customer_id = o.customer_id and {$open} and receivables.due_date < ?) as overdue",
            ]), [today()->toDateString()]);
    }

    public function mapRow(object $row): array
    {
        return [
            ...(array) $row,
            'route_name' => $row->route_name ?: 'Fuera de ruta',
            'average_ticket' => (int) $row->sold_orders > 0 ? (float) $row->delivered / (int) $row->sold_orders : null,
            '_url' => route('customers.show', $row->customer_id, false),
        ];
    }
}
