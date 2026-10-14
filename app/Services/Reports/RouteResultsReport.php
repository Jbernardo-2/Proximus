<?php

namespace App\Services\Reports;

use App\DeliveryRunStatus;
use Illuminate\Database\Query\Builder;

class RouteResultsReport extends Report
{
    public function __construct(SalesFacts $facts, protected DeliveryFacts $deliveries)
    {
        parent::__construct($facts);
    }

    public function key(): string
    {
        return 'resultado-rutas';
    }

    public function group(): string
    {
        return 'Reparto';
    }

    public function title(): string
    {
        return 'Resultado de rutas';
    }

    public function description(): string
    {
        return 'Por jornada: paradas programadas y completadas, tasa de éxito, venta cargada y entregada, cobrado, crédito y mercadería retornada.';
    }

    public function dimensions(): array
    {
        return ['driver', 'vehicle', 'warehouse'];
    }

    public function dateLabel(): ?string
    {
        return 'Fecha de la jornada';
    }

    public function searchPlaceholder(): ?string
    {
        return 'Jornada o repartidor';
    }

    public function summary(ReportFilters $filters): array
    {
        $current = $this->deliveries->totals($filters);
        $previous = $this->deliveries->totals($filters->previous());

        return [
            ['label' => 'Jornadas', 'value' => $current['runs'], 'previous' => $previous['runs'], 'format' => 'integer', 'hint' => $current['completed_stops'].' de '.$current['stops'].' paradas completadas'],
            ['label' => 'Entregas completas', 'value' => self::ratio($current['delivered_stops'], $current['completed_stops']) ?? 0, 'previous' => self::ratio($previous['delivered_stops'], $previous['completed_stops']), 'format' => 'percent', 'hint' => $current['delivered_stops'].' completas · '.$current['partial_stops'].' parciales · '.$current['failed_stops'].' fallidas'],
            ['label' => 'Venta cargada', 'value' => $current['loaded'], 'previous' => $previous['loaded'], 'format' => 'money'],
            ['label' => 'Venta entregada', 'value' => $current['delivered'], 'previous' => $previous['delivered'], 'format' => 'money'],
            ['label' => 'Cobrado', 'value' => $current['collected'], 'previous' => $previous['collected'], 'format' => 'money'],
            ['label' => 'Crédito generado', 'value' => $current['credit'], 'previous' => $previous['credit'], 'format' => 'money', 'lower_is_better' => true],
            ['label' => 'Mercadería retornada', 'value' => $current['returned'], 'previous' => $previous['returned'], 'format' => 'money', 'lower_is_better' => true],
            ['label' => 'Dañado y faltante', 'value' => $current['damaged'] + $current['missing'], 'previous' => $previous['damaged'] + $previous['missing'], 'format' => 'money', 'lower_is_better' => true],
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'scheduled_date', 'label' => 'Fecha', 'format' => 'date', 'sortable' => true],
            ['key' => 'run_number', 'label' => 'Jornada', 'format' => 'text', 'sortable' => true],
            ['key' => 'driver_name', 'label' => 'Repartidor', 'format' => 'text', 'sortable' => true],
            ['key' => 'vehicle', 'label' => 'Vehículo', 'format' => 'text'],
            ['key' => 'status', 'label' => 'Estado', 'format' => 'text'],
            ['key' => 'stops', 'label' => 'Paradas', 'format' => 'integer', 'sortable' => true, 'total' => true],
            ['key' => 'delivered_stops', 'label' => 'Completas', 'format' => 'integer', 'sortable' => true, 'total' => true],
            ['key' => 'partial_stops', 'label' => 'Parciales', 'format' => 'integer', 'sortable' => true, 'total' => true],
            ['key' => 'failed_stops', 'label' => 'Fallidas', 'format' => 'integer', 'sortable' => true, 'total' => true],
            ['key' => 'success_rate', 'label' => 'Tasa de éxito', 'format' => 'percent'],
            ['key' => 'loaded', 'label' => 'Cargado', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'delivered', 'label' => 'Entregado', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'collected', 'label' => 'Cobrado', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'credit', 'label' => 'Crédito', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'returned', 'label' => 'Retornado', 'format' => 'money', 'sortable' => true, 'total' => true],
        ];
    }

    public function defaultSort(): array
    {
        return ['scheduled_date', 'desc'];
    }

    public function query(ReportFilters $filters): ?Builder
    {
        return $this->deliveries->runs($filters)
            ->when($filters->search !== '', fn (Builder $query) => $query->where(function (Builder $match) use ($filters): void {
                $match->where('r.run_number', 'like', "%{$filters->search}%")
                    ->orWhere('r.driver_name', 'like', "%{$filters->search}%");
            }))
            ->selectRaw(implode(', ', [
                'r.id as run_id',
                'r.scheduled_date',
                'r.run_number',
                'r.driver_name',
                'r.vehicle_code',
                'r.vehicle_license_plate',
                'r.status',
                'coalesce(s.stops, 0) as stops',
                'coalesce(s.completed_stops, 0) as completed_stops',
                'coalesce(s.delivered_stops, 0) as delivered_stops',
                'coalesce(s.partial_stops, 0) as partial_stops',
                'coalesce(s.failed_stops, 0) as failed_stops',
                'coalesce(g.loaded_value, 0) as loaded',
                'r.delivered_total as delivered',
                'r.collected_total as collected',
                'r.credit_total as credit',
                'coalesce(g.returned_value, 0) as returned',
            ]));
    }

    public function mapRow(object $row): array
    {
        return [
            ...(array) $row,
            'vehicle' => trim($row->vehicle_code.' '.$row->vehicle_license_plate) ?: null,
            'status' => DeliveryRunStatus::tryFrom((string) $row->status)?->label(),
            'success_rate' => self::ratio((int) $row->delivered_stops, (int) $row->completed_stops),
            '_url' => route('delivery-runs.show', $row->run_id, false),
            '_link' => 'run_number',
        ];
    }
}
