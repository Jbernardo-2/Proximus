<?php

namespace App\Services\Reports;

use App\CustomerCreditStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class CreditUsageReport extends Report
{
    public function key(): string
    {
        return 'uso-credito';
    }

    public function group(): string
    {
        return 'Crédito y cartera';
    }

    public function title(): string
    {
        return 'Uso del crédito';
    }

    public function description(): string
    {
        return 'Límite autorizado, saldo utilizado, disponible y porcentaje de uso de cada cliente, con sus documentos vencidos y el estado del crédito.';
    }

    public function dimensions(): array
    {
        return ['credit_status'];
    }

    public function usesPeriod(): bool
    {
        return false;
    }

    public function searchPlaceholder(): ?string
    {
        return 'Cliente o código';
    }

    public function summary(ReportFilters $filters): array
    {
        $row = DB::query()->fromSub($this->query($filters), 'usage')->selectRaw(implode(', ', [
            'count(*) as customers',
            'coalesce(sum(credit_limit), 0) as credit_limit',
            'coalesce(sum(used), 0) as used',
            'coalesce(sum(overdue), 0) as overdue',
            'coalesce(sum(case when used > credit_limit then 1 else 0 end), 0) as over_limit',
            "coalesce(sum(case when status in ('suspended', 'blocked') then 1 else 0 end), 0) as restricted",
        ]))->first();

        return [
            ['label' => 'Límite autorizado', 'value' => (float) $row->credit_limit, 'format' => 'money', 'hint' => $row->customers.' clientes con perfil de crédito'],
            ['label' => 'Saldo utilizado', 'value' => (float) $row->used, 'format' => 'money', 'hint' => number_format(self::ratio((float) $row->used, (float) $row->credit_limit) ?? 0, 1).' % del límite'],
            ['label' => 'Saldo vencido', 'value' => (float) $row->overdue, 'format' => 'money'],
            ['label' => 'Sobre el límite', 'value' => (int) $row->over_limit, 'format' => 'integer', 'hint' => 'Clientes'],
            ['label' => 'Suspendidos o bloqueados', 'value' => (int) $row->restricted, 'format' => 'integer', 'hint' => 'Clientes'],
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'customer_name', 'label' => 'Cliente', 'format' => 'text', 'sortable' => true],
            ['key' => 'customer_code', 'label' => 'Código', 'format' => 'text', 'sortable' => true],
            ['key' => 'status_label', 'label' => 'Estado del crédito', 'format' => 'text'],
            ['key' => 'credit_limit', 'label' => 'Límite', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'used', 'label' => 'Utilizado', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'available', 'label' => 'Disponible', 'format' => 'money', 'sortable' => true],
            ['key' => 'usage', 'label' => 'Uso', 'format' => 'percent'],
            ['key' => 'overdue', 'label' => 'Vencido', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'overdue_documents', 'label' => 'Documentos vencidos', 'format' => 'integer', 'sortable' => true, 'total' => true],
            ['key' => 'term_days', 'label' => 'Plazo (días)', 'format' => 'integer', 'sortable' => true],
            ['key' => 'grace_days', 'label' => 'Gracia (días)', 'format' => 'integer', 'sortable' => true],
        ];
    }

    public function defaultSort(): array
    {
        return ['used', 'desc'];
    }

    public function query(ReportFilters $filters): ?Builder
    {
        $today = today()->toDateString();
        $balances = DB::table('customer_receivables')
            ->whereIn('status', ['open', 'partially_paid'])
            ->groupBy('customer_id')
            ->selectRaw('customer_id, sum(outstanding_amount) as used, sum(case when due_date < ? then outstanding_amount else 0 end) as overdue, sum(case when due_date < ? then 1 else 0 end) as overdue_documents', [$today, $today]);

        return DB::table('customer_credit_profiles as cp')
            ->join('customers as c', 'c.id', '=', 'cp.customer_id')
            ->leftJoinSub($balances, 'b', 'b.customer_id', '=', 'cp.customer_id')
            ->when($filters->get('credit_status') !== null, fn (Builder $query) => $query->where('cp.status', $filters->get('credit_status')))
            ->when($filters->search !== '', fn (Builder $query) => $query->where(function (Builder $match) use ($filters): void {
                $match->where('c.business_name', 'like', "%{$filters->search}%")
                    ->orWhere('c.code', 'like', "%{$filters->search}%");
            }))
            ->selectRaw(implode(', ', [
                'cp.customer_id',
                'c.business_name as customer_name',
                'c.code as customer_code',
                'cp.status',
                'cp.credit_limit',
                'coalesce(b.used, 0) as used',
                'cp.credit_limit - coalesce(b.used, 0) as available',
                'coalesce(b.overdue, 0) as overdue',
                'coalesce(b.overdue_documents, 0) as overdue_documents',
                'cp.term_days',
                'cp.grace_days',
            ]));
    }

    public function mapRow(object $row): array
    {
        return [
            ...(array) $row,
            'status_label' => CustomerCreditStatus::tryFrom((string) $row->status)?->label(),
            'usage' => self::ratio((float) $row->used, (float) $row->credit_limit),
            '_url' => route('customers.show', $row->customer_id, false).'#customer-credit',
        ];
    }
}
