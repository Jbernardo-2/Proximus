<?php

namespace App\Services\Reports;

use App\DeliveryOutcomeReason;
use Illuminate\Database\Query\Builder;

class FulfillmentReport extends Report
{
    public function key(): string
    {
        return 'cumplimiento';
    }

    public function group(): string
    {
        return 'Pedidos';
    }

    public function title(): string
    {
        return 'Cumplimiento y venta perdida';
    }

    public function description(): string
    {
        return 'Dónde se pierde la venta de los pedidos ya cerrados: de lo solicitado a lo preparado, lo cargado y lo entregado, por producto y motivo.';
    }

    public function searchPlaceholder(): ?string
    {
        return 'Producto o SKU';
    }

    public function summary(ReportFilters $filters): array
    {
        $current = $this->stages($filters);
        $previous = $this->stages($filters->previous());

        return [
            ['label' => 'Solicitado', 'value' => $current['requested'], 'previous' => $previous['requested'], 'format' => 'money', 'hint' => 'Pedidos ya cerrados'],
            ['label' => 'Preparado', 'value' => $current['prepared'], 'previous' => $previous['prepared'], 'format' => 'money', 'hint' => 'No preparado '.number_format($current['requested'] - $current['prepared'], 2)],
            ['label' => 'Cargado', 'value' => $current['loaded'], 'previous' => $previous['loaded'], 'format' => 'money', 'hint' => 'No cargado '.number_format($current['prepared'] - $current['loaded'], 2)],
            ['label' => 'Entregado', 'value' => $current['delivered'], 'previous' => $previous['delivered'], 'format' => 'money', 'hint' => 'No entregado '.number_format($current['loaded'] - $current['delivered'], 2)],
            ['label' => 'Venta perdida', 'value' => $current['requested'] - $current['delivered'], 'previous' => $previous['requested'] - $previous['delivered'], 'format' => 'money', 'lower_is_better' => true],
            ['label' => 'Cumplimiento', 'value' => self::ratio($current['delivered'], $current['requested']) ?? 0, 'previous' => self::ratio($previous['delivered'], $previous['requested']), 'format' => 'percent'],
        ];
    }

    public function charts(ReportFilters $filters): array
    {
        $stages = $this->stages($filters);
        $reasons = $this->facts->ordersWithProductFilters($filters)
            ->whereRaw(SalesFacts::CLOSED)
            ->whereNotNull('ro.outcome_reason')
            ->selectRaw('ro.outcome_reason as reason, sum('.SalesFacts::LOST.') as lost')
            ->groupBy('ro.outcome_reason')
            ->orderByDesc('lost')
            ->get();

        return [
            [
                'title' => 'De lo solicitado a lo entregado',
                'type' => 'bars',
                'format' => 'money',
                'labels' => ['Solicitado', 'Preparado', 'Cargado', 'Entregado'],
                'series' => [['name' => 'Valor', 'color' => ExecutiveSummaryReport::COLOR_DELIVERED, 'values' => [$stages['requested'], $stages['prepared'], $stages['loaded'], $stages['delivered']]]],
            ],
            [
                'title' => 'Venta perdida por motivo',
                'type' => 'bars',
                'format' => 'money',
                'labels' => $reasons->map(fn (object $row): string => DeliveryOutcomeReason::tryFrom((string) $row->reason)?->label() ?? 'Sin motivo')->all(),
                'series' => [['name' => 'Venta perdida', 'color' => ExecutiveSummaryReport::COLOR_FAILED, 'values' => $reasons->map(fn (object $row): float => (float) $row->lost)->all()]],
            ],
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'product_name', 'label' => 'Producto', 'format' => 'text', 'sortable' => true],
            ['key' => 'product_sku', 'label' => 'SKU', 'format' => 'text', 'sortable' => true],
            ['key' => 'presentation_name', 'label' => 'Presentación', 'format' => 'text'],
            ['key' => 'requested_quantity', 'label' => 'Cant. solicitada', 'format' => 'quantity', 'sortable' => true],
            ['key' => 'prepared_quantity', 'label' => 'Preparada', 'format' => 'quantity', 'sortable' => true],
            ['key' => 'loaded_quantity', 'label' => 'Cargada', 'format' => 'quantity', 'sortable' => true],
            ['key' => 'delivered_quantity', 'label' => 'Entregada', 'format' => 'quantity', 'sortable' => true],
            ['key' => 'requested', 'label' => 'Solicitado', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'delivered', 'label' => 'Entregado', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'lost', 'label' => 'Venta perdida', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'fulfillment', 'label' => 'Cumplimiento', 'format' => 'percent'],
        ];
    }

    public function defaultSort(): array
    {
        return ['lost', 'desc'];
    }

    public function query(ReportFilters $filters): ?Builder
    {
        return $this->facts->lines($filters)
            ->whereRaw(SalesFacts::CLOSED)
            ->when($filters->search !== '', fn (Builder $query) => $query->where(function (Builder $match) use ($filters): void {
                $match->where('oi.product_name', 'like', "%{$filters->search}%")
                    ->orWhere('oi.product_sku', 'like', "%{$filters->search}%");
            }))
            ->groupBy('oi.product_id', 'oi.product_presentation_id')
            ->selectRaw(implode(', ', [
                'oi.product_id',
                'min(oi.product_name) as product_name',
                'min(oi.product_sku) as product_sku',
                'min(oi.presentation_name) as presentation_name',
                'sum(oi.quantity) as requested_quantity',
                'sum(coalesce(ri.prepared_quantity, 0)) as prepared_quantity',
                'sum(coalesce(ri.loaded_quantity, 0)) as loaded_quantity',
                'sum(coalesce(ri.delivered_quantity, 0)) as delivered_quantity',
                'sum(oi.line_total) as requested',
                'sum(coalesce(ri.delivered_line_total, 0)) as delivered',
                'sum(oi.line_total) - sum(coalesce(ri.delivered_line_total, 0)) as lost',
            ]));
    }

    public function mapRow(object $row): array
    {
        return [
            ...(array) $row,
            'fulfillment' => self::ratio((float) $row->delivered, (float) $row->requested),
            '_url' => route('products.show', $row->product_id, false),
        ];
    }

    /** @return array{requested: float, prepared: float, loaded: float, delivered: float} */
    private function stages(ReportFilters $filters): array
    {
        $row = $this->facts->lines($filters)
            ->whereRaw(SalesFacts::CLOSED)
            ->selectRaw(implode(', ', [
                'coalesce(sum(oi.line_total), 0) as requested',
                'coalesce(sum('.SalesFacts::LINE_PREPARED.'), 0) as prepared',
                'coalesce(sum('.SalesFacts::LINE_LOADED.'), 0) as loaded',
                'coalesce(sum(coalesce(ri.delivered_line_total, 0)), 0) as delivered',
            ]))
            ->first();

        return [
            'requested' => (float) $row->requested,
            'prepared' => (float) $row->prepared,
            'loaded' => (float) $row->loaded,
            'delivered' => (float) $row->delivered,
        ];
    }
}
