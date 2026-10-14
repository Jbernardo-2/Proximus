<?php

namespace App\Services\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ReceivablesAgingReport extends Report
{
    /** Upper bound in overdue days of each bucket; null is open-ended. */
    private const array BUCKETS = [
        'Vigente' => 0,
        '1–7 días' => 7,
        '8–15 días' => 15,
        '16–30 días' => 30,
        '31–60 días' => 60,
        'Más de 60 días' => null,
    ];

    public function key(): string
    {
        return 'cartera-antiguedad';
    }

    public function group(): string
    {
        return 'Crédito y cartera';
    }

    public function title(): string
    {
        return 'Antigüedad de saldos';
    }

    public function description(): string
    {
        return 'Cada documento pendiente con su vencimiento, lo abonado, el saldo y los días de atraso, clasificado por tramo.';
    }

    public function dimensions(): array
    {
        return [];
    }

    public function usesPeriod(): bool
    {
        return false;
    }

    public function searchPlaceholder(): ?string
    {
        return 'Cliente o documento';
    }

    public function summary(ReportFilters $filters): array
    {
        $buckets = $this->buckets($filters);
        $total = array_sum($buckets);

        return [
            ['label' => 'Saldo por cobrar', 'value' => $total, 'format' => 'money'],
            ...array_map(fn (string $label): array => [
                'label' => $label,
                'value' => $buckets[$label],
                'format' => 'money',
                'hint' => number_format(self::ratio($buckets[$label], $total) ?? 0, 1).' % de la cartera',
            ], array_keys($buckets)),
        ];
    }

    public function charts(ReportFilters $filters): array
    {
        $buckets = $this->buckets($filters);

        return [[
            'title' => 'Cartera por tramo de atraso',
            'type' => 'bars',
            'format' => 'money',
            'labels' => array_keys($buckets),
            'series' => [['name' => 'Saldo', 'color' => ExecutiveSummaryReport::COLOR_COLLECTED, 'values' => array_values($buckets)]],
        ]];
    }

    public function columns(): array
    {
        return [
            ['key' => 'customer_name', 'label' => 'Cliente', 'format' => 'text', 'sortable' => true],
            ['key' => 'document_number', 'label' => 'Documento', 'format' => 'text', 'sortable' => true],
            ['key' => 'issued_at', 'label' => 'Emisión', 'format' => 'date', 'sortable' => true],
            ['key' => 'due_date', 'label' => 'Vencimiento', 'format' => 'date', 'sortable' => true],
            ['key' => 'original_amount', 'label' => 'Saldo original', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'paid_amount', 'label' => 'Abonado', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'outstanding_amount', 'label' => 'Saldo pendiente', 'format' => 'money', 'sortable' => true, 'total' => true],
            ['key' => 'days_overdue', 'label' => 'Días vencidos', 'format' => 'integer'],
            ['key' => 'bucket', 'label' => 'Tramo', 'format' => 'text'],
        ];
    }

    public function defaultSort(): array
    {
        return ['due_date', 'asc'];
    }

    public function query(ReportFilters $filters): ?Builder
    {
        return $this->receivables($filters)->selectRaw(implode(', ', [
            'cr.customer_id',
            'c.business_name as customer_name',
            'cr.document_number',
            'cr.issued_at',
            'cr.due_date',
            'cr.original_amount',
            'cr.original_amount - cr.outstanding_amount as paid_amount',
            'cr.outstanding_amount',
        ]));
    }

    public function mapRow(object $row): array
    {
        $daysOverdue = $row->due_date === null
            ? 0
            : max((int) CarbonImmutable::parse((string) $row->due_date)->startOfDay()->diffInDays(CarbonImmutable::today(), false), 0);
        $bucket = collect(self::BUCKETS)->search(fn (?int $limit): bool => $limit === null || $daysOverdue <= $limit);

        return [
            ...(array) $row,
            'days_overdue' => $daysOverdue,
            'bucket' => $bucket,
            '_url' => route('customers.show', $row->customer_id, false).'#customer-credit',
        ];
    }

    private function receivables(ReportFilters $filters): Builder
    {
        return DB::table('customer_receivables as cr')
            ->join('customers as c', 'c.id', '=', 'cr.customer_id')
            ->whereIn('cr.status', ['open', 'partially_paid'])
            ->when($filters->search !== '', fn (Builder $query) => $query->where(function (Builder $match) use ($filters): void {
                $match->where('c.business_name', 'like', "%{$filters->search}%")
                    ->orWhere('cr.document_number', 'like', "%{$filters->search}%");
            }));
    }

    /** @return array<string, float> */
    private function buckets(ReportFilters $filters): array
    {
        $today = CarbonImmutable::today();
        $selects = [];
        $bindings = [];
        $previousLimit = null;

        foreach (array_values(self::BUCKETS) as $index => $limit) {
            if ($index === 0) {
                $selects[] = "coalesce(sum(case when cr.due_date is null or cr.due_date >= ? then cr.outstanding_amount else 0 end), 0) as bucket_{$index}";
                $bindings[] = $today->toDateString();
            } elseif ($limit === null) {
                $selects[] = "coalesce(sum(case when cr.due_date < ? then cr.outstanding_amount else 0 end), 0) as bucket_{$index}";
                $bindings[] = $today->subDays($previousLimit)->toDateString();
            } else {
                $selects[] = "coalesce(sum(case when cr.due_date < ? and cr.due_date >= ? then cr.outstanding_amount else 0 end), 0) as bucket_{$index}";
                $bindings[] = $today->subDays($previousLimit)->toDateString();
                $bindings[] = $today->subDays($limit)->toDateString();
            }

            $previousLimit = $limit;
        }

        $row = $this->receivables($filters)->selectRaw(implode(', ', $selects), $bindings)->first();

        return collect(array_keys(self::BUCKETS))
            ->mapWithKeys(fn (string $label, int $index): array => [$label => (float) $row->{"bucket_{$index}"}])
            ->all();
    }
}
