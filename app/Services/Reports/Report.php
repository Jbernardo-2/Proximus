<?php

namespace App\Services\Reports;

use Illuminate\Database\Query\Builder;

abstract class Report
{
    public function __construct(protected SalesFacts $facts) {}

    abstract public function key(): string;

    abstract public function group(): string;

    abstract public function title(): string;

    abstract public function description(): string;

    /**
     * Filter dimensions this report honours, besides period and date basis.
     *
     * @return list<string>
     */
    public function dimensions(): array
    {
        return ['route', 'salesperson', 'driver', 'payment_term', 'category', 'brand', 'warehouse'];
    }

    /** False for reports that show balances as of today instead of a period. */
    public function usesPeriod(): bool
    {
        return true;
    }

    /** What the period filters on. Null lets the user choose between order date and delivery date. */
    public function dateLabel(): ?string
    {
        return null;
    }

    public function searchPlaceholder(): ?string
    {
        return null;
    }

    /**
     * @return list<array{label: string, value: float|int, format: string, previous?: float|int|null, hint?: string, lower_is_better?: bool}>
     */
    public function summary(ReportFilters $filters): array
    {
        return [];
    }

    /**
     * @return list<array{title: string, type: string, format: string, labels: list<string>, series: list<array{name: string, color: string, values: list<float|int>}>}>
     */
    public function charts(ReportFilters $filters): array
    {
        return [];
    }

    /**
     * @return list<array{key: string, label: string, format: string, sortable?: bool, total?: bool}>
     */
    public function columns(): array
    {
        return [];
    }

    /** Query whose selected aliases match the column keys. Null for reports without a detail table. */
    public function query(ReportFilters $filters): ?Builder
    {
        return null;
    }

    /** @return array{0: string, 1: string} */
    public function defaultSort(): array
    {
        return [$this->columns()[0]['key'], 'asc'];
    }

    /**
     * @return array<string, mixed>
     */
    public function mapRow(object $row): array
    {
        return (array) $row;
    }

    public function sortedQuery(ReportFilters $filters): ?Builder
    {
        $query = $this->query($filters);

        if ($query === null) {
            return null;
        }

        $sortable = collect($this->columns())->filter(fn (array $column): bool => $column['sortable'] ?? false)->pluck('key');
        [$defaultKey, $defaultDirection] = $this->defaultSort();
        $key = $sortable->contains($filters->sort) ? $filters->sort : $defaultKey;
        $direction = $sortable->contains($filters->sort) ? $filters->direction : $defaultDirection;

        return $query->orderBy($key, $direction);
    }

    protected static function inPeriod(Builder $query, string $column, ReportFilters $filters): Builder
    {
        return $query->where($column, '>=', $filters->from->toDateString())
            ->where($column, '<', $filters->to->addDay()->toDateString());
    }

    protected static function ratio(float|int $part, float|int $whole): ?float
    {
        return $whole > 0 ? round(($part / $whole) * 100, 1) : null;
    }
}
