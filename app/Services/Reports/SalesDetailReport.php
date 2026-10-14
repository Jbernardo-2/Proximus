<?php

namespace App\Services\Reports;

use App\DeliveryOutcomeReason;
use App\OrderStatus;
use App\PaymentTerm;
use Illuminate\Database\Query\Builder;

class SalesDetailReport extends Report
{
    public function key(): string
    {
        return 'ventas-detalle';
    }

    public function group(): string
    {
        return 'Ventas';
    }

    public function title(): string
    {
        return 'Detalle de ventas';
    }

    public function description(): string
    {
        return 'Una fila por pedido: lo solicitado, lo entregado, lo cobrado y el saldo que quedó a crédito.';
    }

    public function dimensions(): array
    {
        return ['route', 'salesperson', 'driver', 'payment_term', 'status', 'category', 'brand', 'warehouse'];
    }

    public function searchPlaceholder(): ?string
    {
        return 'Pedido o cliente';
    }

    public function summary(ReportFilters $filters): array
    {
        $current = $this->facts->totals($filters);
        $previous = $this->facts->totals($filters->previous());

        return [
            ['label' => 'Venta solicitada', 'value' => $current['requested'], 'previous' => $previous['requested'], 'format' => 'money', 'hint' => $current['orders'].' pedidos'],
            ['label' => 'Venta entregada', 'value' => $current['delivered'], 'previous' => $previous['delivered'], 'format' => 'money', 'hint' => $current['customers'].' clientes'],
            ['label' => 'Venta perdida', 'value' => $current['lost'], 'previous' => $previous['lost'], 'format' => 'money', 'lower_is_better' => true],
            ['label' => 'Venta de contado', 'value' => $current['delivered'] - $current['credit'], 'previous' => $previous['delivered'] - $previous['credit'], 'format' => 'money'],
            ['label' => 'Venta a crédito', 'value' => $current['credit'], 'previous' => $previous['credit'], 'format' => 'money', 'lower_is_better' => true],
            ['label' => 'Cobrado en la entrega', 'value' => $current['collected'], 'previous' => $previous['collected'], 'format' => 'money'],
            ['label' => 'Ticket promedio', 'value' => $current['sold_orders'] > 0 ? $current['delivered'] / $current['sold_orders'] : 0, 'previous' => $previous['sold_orders'] > 0 ? $previous['delivered'] / $previous['sold_orders'] : 0, 'format' => 'money'],
            ['label' => 'En proceso', 'value' => $current['in_process'], 'format' => 'money', 'hint' => 'Pedidos aún sin resultado'],
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'order_date', 'label' => 'Fecha', 'format' => 'date', 'sortable' => true],
            ['key' => 'order_number', 'label' => 'Pedido', 'format' => 'text', 'sortable' => true],
            ['key' => 'customer_name', 'label' => 'Cliente', 'format' => 'text', 'sortable' => true],
            ['key' => 'route_name', 'label' => 'Ruta', 'format' => 'text', 'sortable' => true],
            ['key' => 'salesperson_name', 'label' => 'Preventista', 'format' => 'text', 'sortable' => true],
            ['key' => 'driver_name', 'label' => 'Repartidor', 'format' => 'text', 'sortable' => true],
            ['key' => 'requested', 'label' => 'Solicitado', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'delivered', 'label' => 'Entregado', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'collected', 'label' => 'Cobrado', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'credit', 'label' => 'Crédito', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'payment_term', 'label' => 'Condición', 'format' => 'text'],
            ['key' => 'status', 'label' => 'Estado', 'format' => 'text'],
            ['key' => 'outcome_reason', 'label' => 'Motivo', 'format' => 'text'],
        ];
    }

    public function defaultSort(): array
    {
        return ['order_date', 'desc'];
    }

    public function query(ReportFilters $filters): ?Builder
    {
        return $this->facts->ordersWithProductFilters($filters)
            ->when($filters->search !== '', fn (Builder $query) => $query->where(function (Builder $match) use ($filters): void {
                $match->where('o.order_number', 'like', "%{$filters->search}%")
                    ->orWhere('o.customer_name', 'like', "%{$filters->search}%");
            }))
            ->selectRaw(implode(', ', [
                'o.id as order_id',
                'o.order_date',
                'o.order_number',
                'o.customer_name',
                'o.route_name',
                'o.salesperson_name',
                'r.driver_name',
                SalesFacts::REQUESTED.' as requested',
                SalesFacts::DELIVERED.' as delivered',
                SalesFacts::COLLECTED.' as collected',
                SalesFacts::CREDIT.' as credit',
                'o.payment_term',
                'o.status',
                'ro.outcome_reason',
            ]));
    }

    public function mapRow(object $row): array
    {
        return [
            ...(array) $row,
            'route_name' => $row->route_name ?: 'Fuera de ruta',
            'payment_term' => PaymentTerm::tryFrom((string) $row->payment_term)?->label(),
            'status' => OrderStatus::tryFrom((string) $row->status)?->label(),
            'outcome_reason' => DeliveryOutcomeReason::tryFrom((string) $row->outcome_reason)?->label(),
            '_url' => route('orders.show', $row->order_id, false),
            '_link' => 'order_number',
        ];
    }
}
