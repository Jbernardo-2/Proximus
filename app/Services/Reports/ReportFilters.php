<?php

namespace App\Services\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

final class ReportFilters
{
    public const array PERIODS = [
        'today' => 'Hoy',
        'yesterday' => 'Ayer',
        'week' => 'Esta semana',
        'month' => 'Este mes',
        'year' => 'Este año',
        'custom' => 'Personalizado',
    ];

    public const array BASES = [
        'order' => 'Fecha del pedido',
        'delivery' => 'Fecha de entrega',
    ];

    public const array DIMENSIONS = [
        'route', 'salesperson', 'driver', 'customer', 'category', 'brand', 'payment_term', 'status', 'warehouse',
        'vehicle', 'method', 'credit_status', 'visit_type',
    ];

    /** @param array<string, string> $values */
    public function __construct(
        public readonly string $period,
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly string $basis = 'order',
        public readonly array $values = [],
        public readonly string $search = '',
        public readonly ?string $sort = null,
        public readonly string $direction = 'desc',
    ) {}

    public static function fromRequest(Request $request): self
    {
        $period = $request->string('period')->toString();
        $period = array_key_exists($period, self::PERIODS) ? $period : 'month';
        $today = CarbonImmutable::today();
        [$from, $to] = match ($period) {
            'today' => [$today, $today],
            'yesterday' => [$today->subDay(), $today->subDay()],
            'week' => [$today->startOfWeek(), $today],
            'year' => [$today->startOfYear(), $today],
            'custom' => [
                self::date($request->string('from')->toString()) ?? $today->startOfMonth(),
                self::date($request->string('to')->toString()) ?? $today,
            ],
            default => [$today->startOfMonth(), $today],
        };

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        $basis = $request->string('basis')->toString();
        $values = [];

        foreach (self::DIMENSIONS as $dimension) {
            $value = $request->string($dimension)->trim()->toString();

            if ($value !== '') {
                $values[$dimension] = $value;
            }
        }

        return new self(
            period: $period,
            from: $from,
            to: $to,
            basis: array_key_exists($basis, self::BASES) ? $basis : 'order',
            values: $values,
            search: $request->string('search')->trim()->toString(),
            sort: $request->string('sort')->toString() ?: null,
            direction: $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc',
        );
    }

    public function get(string $dimension): ?string
    {
        return $this->values[$dimension] ?? null;
    }

    public function days(): int
    {
        return (int) $this->from->diffInDays($this->to) + 1;
    }

    /** The period of the same length that ends the day before this one starts. */
    public function previous(): self
    {
        $to = $this->from->subDay();

        return new self(
            period: 'custom',
            from: $to->subDays($this->days() - 1),
            to: $to,
            basis: $this->basis,
            values: $this->values,
            search: $this->search,
        );
    }

    public function periodLabel(): string
    {
        return $this->from->equalTo($this->to)
            ? $this->from->format('d/m/Y')
            : $this->from->format('d/m/Y').' – '.$this->to->format('d/m/Y');
    }

    private static function date(string $value): ?CarbonImmutable
    {
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }
}
