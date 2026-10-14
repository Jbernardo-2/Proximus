<?php

namespace App\Services\Reports;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class LoadReconciliationReport extends Report
{
    private const string UNEXPLAINED = 'i.loaded_quantity - i.delivered_quantity - i.returned_quantity - i.damaged_quantity - i.missing_quantity';

    public function __construct(SalesFacts $facts, protected DeliveryFacts $deliveries)
    {
        parent::__construct($facts);
    }

    public function key(): string
    {
        return 'conciliacion-carga';
    }

    public function group(): string
    {
        return 'Reparto';
    }

    public function title(): string
    {
        return 'Conciliación de mercadería';
    }

    public function description(): string
    {
        return 'Por jornada, pedido y producto: lo cargado contra lo entregado, retornado, dañado y faltante, con el valor de cada diferencia.';
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
        return 'Jornada, pedido o producto';
    }

    public function summary(ReportFilters $filters): array
    {
        $row = $this->items($filters)->selectRaw(implode(', ', [
            'coalesce(sum(i.loaded_quantity * i.unit_price), 0) as loaded',
            'coalesce(sum(i.delivered_line_total), 0) as delivered',
            'coalesce(sum(i.returned_quantity * i.unit_price), 0) as returned',
            'coalesce(sum(i.damaged_quantity * i.unit_price), 0) as damaged',
            'coalesce(sum(i.missing_quantity * i.unit_price), 0) as missing',
            'coalesce(sum(('.self::UNEXPLAINED.') * i.unit_price), 0) as unexplained',
        ]))->first();

        return [
            ['label' => 'Cargado', 'value' => (float) $row->loaded, 'format' => 'money'],
            ['label' => 'Entregado', 'value' => (float) $row->delivered, 'format' => 'money'],
            ['label' => 'Retornado', 'value' => (float) $row->returned, 'format' => 'money'],
            ['label' => 'Dañado', 'value' => (float) $row->damaged, 'format' => 'money'],
            ['label' => 'Faltante', 'value' => (float) $row->missing, 'format' => 'money'],
            ['label' => 'Pendiente de conciliar', 'value' => (float) $row->unexplained, 'format' => 'money', 'hint' => 'Cargado que aún no tiene resultado'],
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'scheduled_date', 'label' => 'Fecha', 'format' => 'date', 'sortable' => true],
            ['key' => 'run_number', 'label' => 'Jornada', 'format' => 'text', 'sortable' => true],
            ['key' => 'order_number', 'label' => 'Pedido', 'format' => 'text', 'sortable' => true],
            ['key' => 'customer_name', 'label' => 'Cliente', 'format' => 'text', 'sortable' => true],
            ['key' => 'product_name', 'label' => 'Producto', 'format' => 'text', 'sortable' => true],
            ['key' => 'presentation_name', 'label' => 'Presentación', 'format' => 'text'],
            ['key' => 'loaded_quantity', 'label' => 'Cargado', 'format' => 'quantity', 'sortable' => true],
            ['key' => 'delivered_quantity', 'label' => 'Entregado', 'format' => 'quantity', 'sortable' => true],
            ['key' => 'returned_quantity', 'label' => 'Retornado', 'format' => 'quantity', 'sortable' => true],
            ['key' => 'damaged_quantity', 'label' => 'Dañado', 'format' => 'quantity', 'sortable' => true],
            ['key' => 'missing_quantity', 'label' => 'Faltante', 'format' => 'quantity', 'sortable' => true],
            ['key' => 'unexplained_quantity', 'label' => 'Sin conciliar', 'format' => 'quantity', 'sortable' => true],
            ['key' => 'returned_value', 'label' => 'Valor retornado', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'damaged_value', 'label' => 'Valor dañado', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'missing_value', 'label' => 'Valor faltante', 'format' => 'money', 'sortable' => true, 'total' => true],
        ];
    }

    public function defaultSort(): array
    {
        return ['scheduled_date', 'desc'];
    }

    public function query(ReportFilters $filters): ?Builder
    {
        return $this->items($filters)
            ->when($filters->search !== '', fn (Builder $query) => $query->where(function (Builder $match) use ($filters): void {
                $match->where('period_runs.run_number', 'like', "%{$filters->search}%")
                    ->orWhere('o.order_number', 'like', "%{$filters->search}%")
                    ->orWhere('i.product_name', 'like', "%{$filters->search}%");
            }))
            ->selectRaw(implode(', ', [
                'period_runs.id as run_id',
                'period_runs.scheduled_date',
                'period_runs.run_number',
                'o.order_number',
                'o.customer_name',
                'i.product_name',
                'i.presentation_name',
                'i.loaded_quantity',
                'i.delivered_quantity',
                'i.returned_quantity',
                'i.damaged_quantity',
                'i.missing_quantity',
                self::UNEXPLAINED.' as unexplained_quantity',
                'i.returned_quantity * i.unit_price as returned_value',
                'i.damaged_quantity * i.unit_price as damaged_value',
                'i.missing_quantity * i.unit_price as missing_value',
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

    /** Loaded merchandise lines of the runs in the period. */
    private function items(ReportFilters $filters): Builder
    {
        return DB::table('delivery_run_items as i')
            ->join('delivery_run_orders as ro', 'ro.id', '=', 'i.delivery_run_order_id')
            ->join('orders as o', 'o.id', '=', 'ro.order_id')
            ->joinSub(
                $this->deliveries->runs($filters)->select('r.id', 'r.scheduled_date', 'r.run_number'),
                'period_runs',
                'period_runs.id',
                '=',
                'ro.delivery_run_id',
            )
            ->where('i.loaded_quantity', '>', 0);
    }
}
