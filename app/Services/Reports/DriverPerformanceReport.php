<?php

namespace App\Services\Reports;

use App\DeliveryOutcomeReason;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class DriverPerformanceReport extends Report
{
    public function __construct(SalesFacts $facts, protected DeliveryFacts $deliveries)
    {
        parent::__construct($facts);
    }

    public function key(): string
    {
        return 'rendimiento-repartidor';
    }

    public function group(): string
    {
        return 'Reparto';
    }

    public function title(): string
    {
        return 'Rendimiento de repartidores';
    }

    public function description(): string
    {
        return 'Jornadas, entregas completas, parciales y fallidas, monto entregado y cobrado, diferencias de efectivo y mercadería devuelta, dañada o faltante.';
    }

    public function dimensions(): array
    {
        return ['vehicle', 'warehouse'];
    }

    public function dateLabel(): ?string
    {
        return 'Fecha de la jornada';
    }

    public function charts(ReportFilters $filters): array
    {
        $reasons = DB::table('delivery_run_orders as ro')
            ->joinSub($this->deliveries->runs($filters)->select('r.id'), 'period_runs', 'period_runs.id', '=', 'ro.delivery_run_id')
            ->whereNotNull('ro.outcome_reason')
            ->whereIn('ro.status', ['partially_delivered', 'not_delivered'])
            ->selectRaw('ro.outcome_reason as reason, count(*) as total')
            ->groupBy('ro.outcome_reason')
            ->orderByDesc('total')
            ->get();

        return [[
            'title' => 'Motivos de entregas parciales o fallidas',
            'type' => 'bars',
            'format' => 'integer',
            'labels' => $reasons->map(fn (object $row): string => DeliveryOutcomeReason::tryFrom((string) $row->reason)?->label() ?? 'Sin motivo')->all(),
            'series' => [['name' => 'Paradas', 'color' => ExecutiveSummaryReport::COLOR_FAILED, 'values' => $reasons->map(fn (object $row): int => (int) $row->total)->all()]],
        ]];
    }

    public function columns(): array
    {
        return [
            ['key' => 'driver_name', 'label' => 'Repartidor', 'format' => 'text', 'sortable' => true],
            ['key' => 'runs', 'label' => 'Jornadas', 'format' => 'integer', 'sortable' => true, 'total' => true],
            ['key' => 'completed_stops', 'label' => 'Clientes atendidos', 'format' => 'integer', 'sortable' => true, 'total' => true],
            ['key' => 'delivered_stops', 'label' => 'Completas', 'format' => 'integer', 'sortable' => true, 'total' => true],
            ['key' => 'partial_stops', 'label' => 'Parciales', 'format' => 'integer', 'sortable' => true, 'total' => true],
            ['key' => 'failed_stops', 'label' => 'Fallidas', 'format' => 'integer', 'sortable' => true, 'total' => true],
            ['key' => 'success_rate', 'label' => 'Tasa de éxito', 'format' => 'percent'],
            ['key' => 'delivered', 'label' => 'Entregado', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'collected', 'label' => 'Cobrado', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'credit', 'label' => 'Crédito', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'cash_difference', 'label' => 'Diferencia de efectivo', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'runs_with_difference', 'label' => 'Jornadas con diferencia', 'format' => 'integer', 'sortable' => true, 'total' => true],
            ['key' => 'returned', 'label' => 'Devuelto', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'damaged', 'label' => 'Dañado', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'missing', 'label' => 'Faltante', 'format' => 'money', 'sortable' => true, 'total' => true],
        ];
    }

    public function defaultSort(): array
    {
        return ['delivered', 'desc'];
    }

    public function query(ReportFilters $filters): ?Builder
    {
        return $this->deliveries->runs($filters)
            ->groupBy('r.driver_id')
            ->selectRaw(implode(', ', [
                'r.driver_id',
                'min(r.driver_name) as driver_name',
                'count(*) as runs',
                'coalesce(sum(s.completed_stops), 0) as completed_stops',
                'coalesce(sum(s.delivered_stops), 0) as delivered_stops',
                'coalesce(sum(s.partial_stops), 0) as partial_stops',
                'coalesce(sum(s.failed_stops), 0) as failed_stops',
                'sum(r.delivered_total) as delivered',
                'sum(r.collected_total) as collected',
                'sum(r.credit_total) as credit',
                "sum(case when r.status = 'settled' then r.cash_difference else 0 end) as cash_difference",
                "sum(case when r.status = 'settled' and r.cash_difference <> 0 then 1 else 0 end) as runs_with_difference",
                'coalesce(sum(g.returned_value), 0) as returned',
                'coalesce(sum(g.damaged_value), 0) as damaged',
                'coalesce(sum(g.missing_value), 0) as missing',
            ]));
    }

    public function mapRow(object $row): array
    {
        return [
            ...(array) $row,
            'success_rate' => self::ratio((int) $row->delivered_stops, (int) $row->completed_stops),
        ];
    }
}
