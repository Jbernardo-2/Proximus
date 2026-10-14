<?php

namespace App\Services\Reports;

use App\PaymentMethod;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class PaymentsReport extends Report
{
    public function key(): string
    {
        return 'cobros';
    }

    public function group(): string
    {
        return 'Cobros y liquidaciones';
    }

    public function title(): string
    {
        return 'Cobros recibidos por método';
    }

    public function description(): string
    {
        return 'Cada cobro recibido, separando los hechos en la entrega de los abonos a cuenta, con método, referencia y pagos anulados.';
    }

    public function dimensions(): array
    {
        return ['method'];
    }

    public function dateLabel(): ?string
    {
        return 'Fecha de cobro';
    }

    public function searchPlaceholder(): ?string
    {
        return 'Cliente, recibo o referencia';
    }

    public function summary(ReportFilters $filters): array
    {
        $current = $this->totals($filters);
        $previous = $this->totals($filters->previous());

        return [
            ['label' => 'Total cobrado', 'value' => $current['collected'], 'previous' => $previous['collected'], 'format' => 'money', 'hint' => $current['operations'].' operaciones'],
            ['label' => 'Cobrado en entregas', 'value' => $current['on_delivery'], 'previous' => $previous['on_delivery'], 'format' => 'money'],
            ['label' => 'Abonos a cuenta', 'value' => $current['on_account'], 'previous' => $previous['on_account'], 'format' => 'money'],
            ['label' => 'Pagos anulados', 'value' => $current['voided'], 'previous' => $previous['voided'], 'format' => 'money', 'lower_is_better' => true, 'hint' => $current['voided_operations'].' operaciones'],
            ['label' => 'Sin referencia', 'value' => $current['missing_references'], 'format' => 'integer', 'hint' => 'Transferencias, tarjetas o cheques'],
        ];
    }

    public function charts(ReportFilters $filters): array
    {
        $methods = $this->payments($filters)
            ->where('status', 'active')
            ->selectRaw('method, count(*) as operations, sum(amount) as total')
            ->groupBy('method')
            ->orderByDesc('total')
            ->get();
        $colors = ['#188365', '#2f6fd0', '#8a56c2', '#d98a00', '#c2410c'];

        return [[
            'title' => 'Cobrado por método de pago',
            'type' => 'parts',
            'format' => 'money',
            'labels' => $methods->map(fn (object $row): string => $this->methodLabel($row->method))->all(),
            'series' => $methods->values()->map(fn (object $row, int $index): array => [
                'name' => $this->methodLabel($row->method).' ('.$row->operations.')',
                'color' => $colors[$index % count($colors)],
                'values' => [(float) $row->total],
            ])->all(),
        ]];
    }

    public function columns(): array
    {
        return [
            ['key' => 'paid_at', 'label' => 'Fecha y hora', 'format' => 'datetime', 'sortable' => true],
            ['key' => 'receipt_number', 'label' => 'Recibo', 'format' => 'text', 'sortable' => true],
            ['key' => 'customer_name', 'label' => 'Cliente', 'format' => 'text', 'sortable' => true],
            ['key' => 'origin', 'label' => 'Origen', 'format' => 'text', 'sortable' => true],
            ['key' => 'document', 'label' => 'Pedido', 'format' => 'text'],
            ['key' => 'run_number', 'label' => 'Jornada', 'format' => 'text'],
            ['key' => 'received_by', 'label' => 'Recibió', 'format' => 'text', 'sortable' => true],
            ['key' => 'method', 'label' => 'Método', 'format' => 'text', 'sortable' => true],
            ['key' => 'reference', 'label' => 'Referencia', 'format' => 'text'],
            ['key' => 'amount', 'label' => 'Monto', 'format' => 'money', 'sortable' => true],
            ['key' => 'status', 'label' => 'Estado', 'format' => 'text', 'sortable' => true],
        ];
    }

    public function defaultSort(): array
    {
        return ['paid_at', 'desc'];
    }

    public function query(ReportFilters $filters): ?Builder
    {
        return $this->payments($filters)
            ->when($filters->search !== '', fn (Builder $query) => $query->where(function (Builder $match) use ($filters): void {
                $match->where('customer_name', 'like', "%{$filters->search}%")
                    ->orWhere('receipt_number', 'like', "%{$filters->search}%")
                    ->orWhere('reference', 'like', "%{$filters->search}%");
            }))
            ->select('*');
    }

    public function mapRow(object $row): array
    {
        return [
            ...(array) $row,
            'origin' => $row->origin === 'delivery' ? 'Entrega' : 'Abono a cuenta',
            'method' => $this->methodLabel($row->method),
            'status' => $row->status === 'active' ? 'Válido' : 'Anulado',
            '_url' => route('customers.show', $row->customer_id, false),
            '_link' => 'customer_name',
        ];
    }

    /** Every payment line received in the period, from deliveries and from account payments. */
    private function payments(ReportFilters $filters): Builder
    {
        $onDelivery = DB::table('delivery_payments as dp')
            ->join('delivery_run_orders as ro', 'ro.id', '=', 'dp.delivery_run_order_id')
            ->join('orders as o', 'o.id', '=', 'ro.order_id')
            ->join('delivery_runs as r', 'r.id', '=', 'ro.delivery_run_id')
            ->leftJoin('users as u', 'u.id', '=', 'dp.received_by')
            ->selectRaw(implode(', ', [
                'dp.received_at as paid_at',
                'dp.receipt_number',
                'o.customer_id',
                'o.customer_name',
                "'delivery' as origin",
                'o.order_number as document',
                'r.run_number',
                'u.name as received_by',
                'dp.method',
                'dp.reference',
                'dp.amount',
                'dp.status',
            ]));
        $onAccount = DB::table('customer_account_entries as e')
            ->join('customers as c', 'c.id', '=', 'e.customer_id')
            ->leftJoin('customer_payment_lines as l', 'l.customer_account_entry_id', '=', 'e.id')
            ->leftJoin('users as u', 'u.id', '=', 'e.created_by')
            ->where('e.type', 'customer_payment')
            ->selectRaw(implode(', ', [
                'e.occurred_at as paid_at',
                'e.receipt_number',
                'e.customer_id',
                'c.business_name as customer_name',
                "'account' as origin",
                'null as document',
                'null as run_number',
                'u.name as received_by',
                'coalesce(l.method, e.method) as method',
                'coalesce(l.reference, e.reference) as reference',
                'coalesce(l.amount, e.credit_amount) as amount',
                "case when e.status = 'reversed' then 'voided' else 'active' end as status",
            ]));

        return self::inPeriod(DB::query()->fromSub($onDelivery->unionAll($onAccount), 'payments'), 'paid_at', $filters)
            ->when($filters->get('method') !== null, fn (Builder $query) => $query->where('method', $filters->get('method')));
    }

    /** @return array{collected: float, operations: int, on_delivery: float, on_account: float, voided: float, voided_operations: int, missing_references: int} */
    private function totals(ReportFilters $filters): array
    {
        $row = $this->payments($filters)->selectRaw(implode(', ', [
            "coalesce(sum(case when status = 'active' then amount else 0 end), 0) as collected",
            "coalesce(sum(case when status = 'active' then 1 else 0 end), 0) as operations",
            "coalesce(sum(case when status = 'active' and origin = 'delivery' then amount else 0 end), 0) as on_delivery",
            "coalesce(sum(case when status = 'active' and origin = 'account' then amount else 0 end), 0) as on_account",
            "coalesce(sum(case when status <> 'active' then amount else 0 end), 0) as voided",
            "coalesce(sum(case when status <> 'active' then 1 else 0 end), 0) as voided_operations",
            "coalesce(sum(case when status = 'active' and method in ('bank_transfer', 'card', 'check') and (reference is null or reference = '') then 1 else 0 end), 0) as missing_references",
        ]))->first();

        return [
            'collected' => (float) $row->collected,
            'operations' => (int) $row->operations,
            'on_delivery' => (float) $row->on_delivery,
            'on_account' => (float) $row->on_account,
            'voided' => (float) $row->voided,
            'voided_operations' => (int) $row->voided_operations,
            'missing_references' => (int) $row->missing_references,
        ];
    }

    private function methodLabel(?string $method): string
    {
        return PaymentMethod::tryFrom((string) $method)?->label() ?? 'Sin método';
    }
}
