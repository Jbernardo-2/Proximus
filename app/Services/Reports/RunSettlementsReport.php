<?php

namespace App\Services\Reports;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class RunSettlementsReport extends Report
{
    public function key(): string
    {
        return 'liquidaciones';
    }

    public function group(): string
    {
        return 'Cobros y liquidaciones';
    }

    public function title(): string
    {
        return 'Liquidaciones y diferencias de caja';
    }

    public function description(): string
    {
        return 'Por jornada liquidada: efectivo esperado y declarado, diferencia, otros métodos, crédito concedido, quién liquidó y sus observaciones.';
    }

    public function dimensions(): array
    {
        return ['driver', 'warehouse'];
    }

    public function dateLabel(): ?string
    {
        return 'Fecha de liquidación';
    }

    public function searchPlaceholder(): ?string
    {
        return 'Jornada o repartidor';
    }

    public function summary(ReportFilters $filters): array
    {
        $row = $this->settlements($filters)->selectRaw(implode(', ', [
            'count(*) as runs',
            'coalesce(sum(r.cash_expected), 0) as expected',
            'coalesce(sum(r.cash_declared), 0) as declared',
            'coalesce(sum(r.cash_difference), 0) as difference',
            'coalesce(sum(case when r.cash_difference < 0 then r.cash_difference else 0 end), 0) as shortage',
            'coalesce(sum(case when r.cash_difference > 0 then r.cash_difference else 0 end), 0) as surplus',
            'coalesce(sum(case when r.cash_difference < 0 then 1 else 0 end), 0) as shortage_runs',
            'coalesce(sum(case when r.cash_difference > 0 then 1 else 0 end), 0) as surplus_runs',
        ]))->first();

        return [
            ['label' => 'Jornadas liquidadas', 'value' => (int) $row->runs, 'format' => 'integer'],
            ['label' => 'Efectivo esperado', 'value' => (float) $row->expected, 'format' => 'money'],
            ['label' => 'Efectivo declarado', 'value' => (float) $row->declared, 'format' => 'money'],
            ['label' => 'Diferencia neta', 'value' => (float) $row->difference, 'format' => 'money'],
            ['label' => 'Faltantes', 'value' => (float) $row->shortage, 'format' => 'money', 'hint' => $row->shortage_runs.' jornadas'],
            ['label' => 'Sobrantes', 'value' => (float) $row->surplus, 'format' => 'money', 'hint' => $row->surplus_runs.' jornadas'],
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'settled_at', 'label' => 'Liquidada', 'format' => 'datetime', 'sortable' => true],
            ['key' => 'run_number', 'label' => 'Jornada', 'format' => 'text', 'sortable' => true],
            ['key' => 'driver_name', 'label' => 'Repartidor', 'format' => 'text', 'sortable' => true],
            ['key' => 'cash_expected', 'label' => 'Efectivo esperado', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'cash_declared', 'label' => 'Efectivo declarado', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'cash_difference', 'label' => 'Diferencia', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'transfer_total', 'label' => 'Transferencias', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'card_total', 'label' => 'Tarjetas', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'check_total', 'label' => 'Cheques', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'other_payment_total', 'label' => 'Otros', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'credit_total', 'label' => 'Crédito', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'delivered_total', 'label' => 'Entregado', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'collected_total', 'label' => 'Cobrado', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'settled_by', 'label' => 'Liquidó', 'format' => 'text'],
            ['key' => 'settlement_notes', 'label' => 'Observaciones', 'format' => 'text'],
        ];
    }

    public function defaultSort(): array
    {
        return ['settled_at', 'desc'];
    }

    public function query(ReportFilters $filters): ?Builder
    {
        return $this->settlements($filters)
            ->leftJoin('users as u', 'u.id', '=', 'r.settled_by')
            ->when($filters->search !== '', fn (Builder $query) => $query->where(function (Builder $match) use ($filters): void {
                $match->where('r.run_number', 'like', "%{$filters->search}%")
                    ->orWhere('r.driver_name', 'like', "%{$filters->search}%");
            }))
            ->selectRaw(implode(', ', [
                'r.id as run_id',
                'r.settled_at',
                'r.run_number',
                'r.driver_name',
                'r.cash_expected',
                'r.cash_declared',
                'r.cash_difference',
                'r.transfer_total',
                'r.card_total',
                'r.check_total',
                'r.other_payment_total',
                'r.credit_total',
                'r.delivered_total',
                'r.collected_total',
                'u.name as settled_by',
                'r.settlement_notes',
            ]));
    }

    public function mapRow(object $row): array
    {
        return [
            ...(array) $row,
            '_url' => route('delivery-runs.show', $row->run_id, false),
            '_link' => 'run_number',
        ];
    }

    private function settlements(ReportFilters $filters): Builder
    {
        return self::inPeriod(DB::table('delivery_runs as r')->where('r.status', 'settled'), 'r.settled_at', $filters)
            ->when($filters->get('driver') !== null, fn (Builder $query) => $query->where('r.driver_id', $filters->get('driver')))
            ->when($filters->get('warehouse') !== null, fn (Builder $query) => $query->where('r.warehouse_id', $filters->get('warehouse')));
    }
}
