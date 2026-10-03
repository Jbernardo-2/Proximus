<?php

namespace App\Services;

use App\Models\PriceTier;
use App\Models\ProductPresentation;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class PresentationPriceResolver
{
    /**
     * @return array{unit_price: string, source: string, price_tier_id: string|null}
     */
    public function resolve(ProductPresentation $presentation, string $quantity, ?CarbonInterface $at = null): array
    {
        $date = CarbonImmutable::instance($at ?? now())->startOfDay();
        $presentation->loadMissing('priceTiers');

        /** @var PriceTier|null $tier */
        $tier = $presentation->priceTiers
            ->filter(function (PriceTier $tier) use ($quantity, $date): bool {
                if (! $tier->is_active || bccomp($quantity, $tier->min_quantity, 6) < 0) {
                    return false;
                }

                if ($tier->max_quantity !== null && bccomp($quantity, $tier->max_quantity, 6) > 0) {
                    return false;
                }

                if ($tier->starts_at !== null && $tier->starts_at->startOfDay()->isAfter($date)) {
                    return false;
                }

                return $tier->ends_at === null || ! $tier->ends_at->startOfDay()->isBefore($date);
            })
            ->sort(fn (PriceTier $left, PriceTier $right): int => bccomp($right->min_quantity, $left->min_quantity, 6))
            ->first();

        return [
            'unit_price' => $tier?->unit_price ?? $presentation->sale_price,
            'source' => $tier === null ? 'presentation' : 'price_tier',
            'price_tier_id' => $tier?->id,
        ];
    }
}
