<?php

namespace App\Services\Reports;

use Illuminate\Database\Query\Builder;

class SalesByProductReport extends Report
{
    public function key(): string
    {
        return 'ventas-producto';
    }

    public function group(): string
    {
        return 'Ventas';
    }

    public function title(): string
    {
        return 'Ventas por producto y presentación';
    }

    public function description(): string
    {
        return 'Cantidad solicitada y entregada, importe, precio promedio, clientes y mercadería devuelta, dañada o faltante.';
    }

    public function searchPlaceholder(): ?string
    {
        return 'Producto, SKU o código de barras';
    }

    public function summary(ReportFilters $filters): array
    {
        $current = $this->totals($filters);
        $previous = $this->totals($filters->previous());

        return [
            ['label' => 'Importe entregado', 'value' => $current['delivered'], 'previous' => $previous['delivered'], 'format' => 'money'],
            ['label' => 'Importe solicitado', 'value' => $current['requested'], 'previous' => $previous['requested'], 'format' => 'money'],
            ['label' => 'Productos vendidos', 'value' => $current['products'], 'previous' => $previous['products'], 'format' => 'integer', 'hint' => 'Con al menos una entrega'],
            ['label' => 'Presentaciones entregadas', 'value' => $current['delivered_quantity'], 'previous' => $previous['delivered_quantity'], 'format' => 'quantity', 'hint' => 'Cajas, paquetes y unidades'],
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'product_name', 'label' => 'Producto', 'format' => 'text', 'sortable' => true],
            ['key' => 'product_sku', 'label' => 'SKU', 'format' => 'text', 'sortable' => true],
            ['key' => 'barcode', 'label' => 'Código de barras', 'format' => 'text'],
            ['key' => 'category_name', 'label' => 'Categoría', 'format' => 'text', 'sortable' => true],
            ['key' => 'brand_name', 'label' => 'Marca', 'format' => 'text', 'sortable' => true],
            ['key' => 'presentation_name', 'label' => 'Presentación', 'format' => 'text'],
            ['key' => 'requested_quantity', 'label' => 'Cant. solicitada', 'format' => 'quantity', 'sortable' => true],
            ['key' => 'delivered_quantity', 'label' => 'Cant. entregada', 'format' => 'quantity', 'sortable' => true],
            ['key' => 'delivered_base_quantity', 'label' => 'En unidad base', 'format' => 'quantity', 'sortable' => true],
            ['key' => 'delivered', 'label' => 'Importe vendido', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'average_price', 'label' => 'Precio promedio', 'format' => 'money'],
            ['key' => 'customers', 'label' => 'Clientes', 'format' => 'integer', 'sortable' => true],
            ['key' => 'orders', 'label' => 'Pedidos', 'format' => 'integer', 'sortable' => true],
            ['key' => 'returned_quantity', 'label' => 'Devuelto', 'format' => 'quantity', 'sortable' => true],
            ['key' => 'damaged_quantity', 'label' => 'Dañado', 'format' => 'quantity', 'sortable' => true],
            ['key' => 'missing_quantity', 'label' => 'Faltante', 'format' => 'quantity', 'sortable' => true],
            ['key' => 'fulfillment', 'label' => 'Cumplimiento', 'format' => 'percent'],
        ];
    }

    public function defaultSort(): array
    {
        return ['delivered', 'desc'];
    }

    public function query(ReportFilters $filters): ?Builder
    {
        $closed = SalesFacts::CLOSED;

        return $this->facts->lines($filters)
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->leftJoin('product_presentations as pp', 'pp.id', '=', 'oi.product_presentation_id')
            ->where('o.status', '!=', 'cancelled')
            ->when($filters->search !== '', fn (Builder $query) => $query->where(function (Builder $match) use ($filters): void {
                $match->where('oi.product_name', 'like', "%{$filters->search}%")
                    ->orWhere('oi.product_sku', 'like', "%{$filters->search}%")
                    ->orWhere('pp.barcode', 'like', "%{$filters->search}%");
            }))
            ->groupBy('oi.product_id', 'oi.product_presentation_id')
            ->selectRaw(implode(', ', [
                'oi.product_id',
                'min(oi.product_name) as product_name',
                'min(oi.product_sku) as product_sku',
                'min(pp.barcode) as barcode',
                'min(c.name) as category_name',
                'min(b.name) as brand_name',
                'min(oi.presentation_name) as presentation_name',
                'min(oi.base_unit_symbol) as base_unit_symbol',
                'sum('.SalesFacts::LINE_REQUESTED_QUANTITY.') as requested_quantity',
                'sum('.SalesFacts::LINE_DELIVERED_QUANTITY.') as delivered_quantity',
                "sum(case when {$closed} then coalesce(ri.delivered_base_quantity, 0) else 0 end) as delivered_base_quantity",
                'sum('.SalesFacts::LINE_DELIVERED.') as delivered',
                "count(distinct case when {$closed} and coalesce(ri.delivered_quantity, 0) > 0 then o.customer_id end) as customers",
                "count(distinct case when {$closed} and coalesce(ri.delivered_quantity, 0) > 0 then o.id end) as orders",
                "sum(case when {$closed} then coalesce(ri.returned_quantity, 0) else 0 end) as returned_quantity",
                "sum(case when {$closed} then coalesce(ri.damaged_quantity, 0) else 0 end) as damaged_quantity",
                "sum(case when {$closed} then coalesce(ri.missing_quantity, 0) else 0 end) as missing_quantity",
                "sum(case when {$closed} then oi.quantity else 0 end) as closed_requested_quantity",
            ]));
    }

    public function mapRow(object $row): array
    {
        return [
            ...(array) $row,
            'average_price' => (float) $row->delivered_quantity > 0 ? (float) $row->delivered / (float) $row->delivered_quantity : null,
            'fulfillment' => self::ratio((float) $row->delivered_quantity, (float) $row->closed_requested_quantity),
            '_url' => route('products.show', $row->product_id, false),
        ];
    }

    /** @return array{requested: float, delivered: float, delivered_quantity: float, products: int} */
    private function totals(ReportFilters $filters): array
    {
        $row = $this->facts->lines($filters)->selectRaw(implode(', ', [
            'coalesce(sum('.SalesFacts::LINE_REQUESTED.'), 0) as requested',
            'coalesce(sum('.SalesFacts::LINE_DELIVERED.'), 0) as delivered',
            'coalesce(sum('.SalesFacts::LINE_DELIVERED_QUANTITY.'), 0) as delivered_quantity',
            'count(distinct case when '.SalesFacts::CLOSED.' and coalesce(ri.delivered_quantity, 0) > 0 then oi.product_id end) as products',
        ]))->first();

        return [
            'requested' => (float) $row->requested,
            'delivered' => (float) $row->delivered,
            'delivered_quantity' => (float) $row->delivered_quantity,
            'products' => (int) $row->products,
        ];
    }
}
