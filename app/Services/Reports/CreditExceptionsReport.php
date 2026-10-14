<?php

namespace App\Services\Reports;

use App\CustomerCreditAuthorizationType;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class CreditExceptionsReport extends Report
{
    public function key(): string
    {
        return 'excepciones-credito';
    }

    public function group(): string
    {
        return 'Crédito y cartera';
    }

    public function title(): string
    {
        return 'Autorizaciones excepcionales de crédito';
    }

    public function description(): string
    {
        return 'Ventas que excedieron el límite del cliente: exposición anterior, límite, importe excedido, motivo y quién autorizó.';
    }

    public function dimensions(): array
    {
        return [];
    }

    public function dateLabel(): ?string
    {
        return 'Fecha de autorización';
    }

    public function searchPlaceholder(): ?string
    {
        return 'Cliente o pedido';
    }

    public function summary(ReportFilters $filters): array
    {
        $current = $this->totals($filters);
        $previous = $this->totals($filters->previous());

        return [
            ['label' => 'Autorizaciones', 'value' => $current['count'], 'previous' => $previous['count'], 'format' => 'integer', 'lower_is_better' => true, 'hint' => $current['customers'].' clientes'],
            ['label' => 'Importe excedido', 'value' => $current['exceeded'], 'previous' => $previous['exceeded'], 'format' => 'money', 'lower_is_better' => true],
            ['label' => 'Crédito autorizado', 'value' => $current['requested'], 'previous' => $previous['requested'], 'format' => 'money'],
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'authorized_at', 'label' => 'Fecha y hora', 'format' => 'datetime', 'sortable' => true],
            ['key' => 'customer_name', 'label' => 'Cliente', 'format' => 'text', 'sortable' => true],
            ['key' => 'type', 'label' => 'Momento', 'format' => 'text'],
            ['key' => 'order_number', 'label' => 'Pedido', 'format' => 'text', 'sortable' => true],
            ['key' => 'exposure_before', 'label' => 'Exposición anterior', 'format' => 'money', 'sortable' => true],
            ['key' => 'credit_limit', 'label' => 'Límite', 'format' => 'money', 'sortable' => true],
            ['key' => 'requested_amount', 'label' => 'Crédito solicitado', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'exceeded_by', 'label' => 'Excedido', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'reason', 'label' => 'Motivo', 'format' => 'text'],
            ['key' => 'authorized_by', 'label' => 'Autorizó', 'format' => 'text', 'sortable' => true],
        ];
    }

    public function defaultSort(): array
    {
        return ['authorized_at', 'desc'];
    }

    public function query(ReportFilters $filters): ?Builder
    {
        return $this->authorizations($filters)
            ->leftJoin('users as u', 'u.id', '=', 'a.authorized_by')
            ->when($filters->search !== '', fn (Builder $query) => $query->where(function (Builder $match) use ($filters): void {
                $match->where('c.business_name', 'like', "%{$filters->search}%")
                    ->orWhere('o.order_number', 'like', "%{$filters->search}%");
            }))
            ->selectRaw(implode(', ', [
                'a.customer_id',
                'a.authorized_at',
                'c.business_name as customer_name',
                'a.type',
                'o.order_number',
                'a.exposure_before',
                'a.credit_limit',
                'a.requested_amount',
                'a.exceeded_by',
                'a.reason',
                'u.name as authorized_by',
            ]));
    }

    public function mapRow(object $row): array
    {
        return [
            ...(array) $row,
            'type' => CustomerCreditAuthorizationType::tryFrom((string) $row->type)?->label(),
            '_url' => route('customers.show', $row->customer_id, false).'#customer-credit',
            '_link' => 'customer_name',
        ];
    }

    private function authorizations(ReportFilters $filters): Builder
    {
        return self::inPeriod(
            DB::table('customer_credit_authorizations as a')
                ->join('customers as c', 'c.id', '=', 'a.customer_id')
                ->leftJoin('orders as o', 'o.id', '=', 'a.order_id'),
            'a.authorized_at',
            $filters,
        );
    }

    /** @return array{count: int, customers: int, exceeded: float, requested: float} */
    private function totals(ReportFilters $filters): array
    {
        $row = $this->authorizations($filters)
            ->selectRaw('count(*) as total, count(distinct a.customer_id) as customers, coalesce(sum(a.exceeded_by), 0) as exceeded, coalesce(sum(a.requested_amount), 0) as requested')
            ->first();

        return [
            'count' => (int) $row->total,
            'customers' => (int) $row->customers,
            'exceeded' => (float) $row->exceeded,
            'requested' => (float) $row->requested,
        ];
    }
}
