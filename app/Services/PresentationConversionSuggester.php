<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductPresentation;
use Carbon\CarbonInterface;
use InvalidArgumentException;

class PresentationConversionSuggester
{
    private const SCALE = 6;

    public function __construct(private PresentationPriceResolver $priceResolver) {}

    /**
     * @return array<string, mixed>
     */
    public function suggest(Product $product, string $baseQuantity, ?CarbonInterface $at = null): array
    {
        if (bccomp($baseQuantity, '0', self::SCALE) <= 0) {
            throw new InvalidArgumentException('La cantidad debe ser mayor que cero.');
        }

        $product->loadMissing(['baseUnit', 'presentations.priceTiers']);

        $presentations = $product->presentations
            ->filter(fn (ProductPresentation $presentation): bool => $presentation->is_active && $presentation->is_sellable)
            ->sort(fn (ProductPresentation $left, ProductPresentation $right): int => bccomp($right->conversion_factor, $left->conversion_factor, self::SCALE));

        $remaining = $baseQuantity;
        $total = '0';
        $components = [];

        foreach ($presentations as $presentation) {
            if (bccomp($remaining, '0', self::SCALE) <= 0) {
                break;
            }

            $countScale = $presentation->is_base && $product->allows_decimal ? self::SCALE : 0;
            $count = bcdiv($remaining, $presentation->conversion_factor, $countScale);

            if (bccomp($count, '0', self::SCALE) <= 0) {
                continue;
            }

            $coveredQuantity = bcmul($count, $presentation->conversion_factor, self::SCALE);
            $remaining = bcsub($remaining, $coveredQuantity, self::SCALE);
            $price = $this->priceResolver->resolve($presentation, $count, $at);
            $subtotal = bcmul($count, $price['unit_price'], 4);
            $total = bcadd($total, $subtotal, 4);

            $components[] = [
                'presentation_id' => $presentation->id,
                'name' => $presentation->name,
                'count' => $this->normalize($count),
                'conversion_factor' => $this->normalize($presentation->conversion_factor),
                'base_quantity' => $this->normalize($coveredQuantity),
                'unit_price' => $price['unit_price'],
                'price_source' => $price['source'],
                'price_tier_id' => $price['price_tier_id'],
                'subtotal' => $subtotal,
            ];
        }

        return [
            'product_id' => $product->id,
            'requested_base_quantity' => $this->normalize($baseQuantity),
            'base_unit' => [
                'id' => $product->baseUnit->id,
                'name' => $product->baseUnit->name,
                'symbol' => $product->baseUnit->symbol,
            ],
            'components' => $components,
            'remaining_base_quantity' => $this->normalize($remaining),
            'is_exact' => bccomp($remaining, '0', self::SCALE) === 0,
            'estimated_total' => $total,
            'notice' => 'Esta es una sugerencia. Las cantidades solo cambian cuando el usuario las confirma.',
        ];
    }

    private function normalize(string $number): string
    {
        $normalized = str_contains($number, '.')
            ? rtrim(rtrim($number, '0'), '.')
            : $number;

        return $normalized === '' || $normalized === '-0' ? '0' : $normalized;
    }
}
