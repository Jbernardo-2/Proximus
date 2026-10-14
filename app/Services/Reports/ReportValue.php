<?php

namespace App\Services\Reports;

use Carbon\CarbonImmutable;

final class ReportValue
{
    public static function format(mixed $value, string $format, string $currency): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return match ($format) {
            'money' => $currency.' '.number_format((float) $value, 2),
            'integer' => number_format((int) $value),
            'quantity' => rtrim(rtrim(number_format((float) $value, 2), '0'), '.'),
            'percent' => number_format((float) $value, 1).' %',
            'date' => CarbonImmutable::parse((string) $value)->format('d/m/Y'),
            'datetime' => CarbonImmutable::parse((string) $value)->format('d/m/Y H:i'),
            default => (string) $value,
        };
    }

    public static function isNumeric(string $format): bool
    {
        return in_array($format, ['money', 'integer', 'quantity', 'percent'], true);
    }

    /**
     * Change against the previous period: relative for amounts, in points for percentages.
     *
     * @return array{text: string, direction: int}|null
     */
    public static function change(float|int $current, float|int|null $previous, string $format): ?array
    {
        if ($previous === null) {
            return null;
        }

        if ($format === 'percent') {
            $difference = round($current - $previous, 1);

            return ['text' => ($difference > 0 ? '+' : '').number_format($difference, 1).' pts', 'direction' => $difference <=> 0];
        }

        if ((float) $previous === 0.0) {
            return null;
        }

        $change = round((($current - $previous) / abs($previous)) * 100, 1);

        return ['text' => ($change > 0 ? '+' : '').number_format($change, 1).' %', 'direction' => $change <=> 0];
    }
}
