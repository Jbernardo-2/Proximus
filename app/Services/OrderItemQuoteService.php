<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ProductPresentation;

class OrderItemQuoteService
{
    public function __construct(
        private PresentationPriceResolver $priceResolver,
        private PresentationConversionSuggester $conversionSuggester,
    ) {}

    /** @return array<string, mixed> */
    public function quote(Order $order, ProductPresentation $presentation, string $quantity): array
    {
        $presentation->loadMissing(['product.baseUnit', 'product.presentations.priceTiers', 'priceTiers']);
        $price = $this->priceResolver->resolve($presentation, $quantity, $order->order_date);
        $baseQuantity = bcmul($quantity, $presentation->conversion_factor, 6);

        return [
            'selected' => [
                'product_id' => $presentation->product_id,
                'product_name' => $presentation->product->name,
                'presentation_id' => $presentation->id,
                'presentation_name' => $presentation->name,
                'quantity' => $quantity,
                'conversion_factor' => $presentation->conversion_factor,
                'base_quantity' => $baseQuantity,
                'base_unit_symbol' => $presentation->product->baseUnit->symbol,
                'standard_unit_price' => $price['unit_price'],
                'price_source' => $price['source'],
                'price_tier_id' => $price['price_tier_id'],
                'line_total' => bcmul($quantity, $price['unit_price'], 4),
            ],
            'conversion_suggestion' => $this->conversionSuggester->suggest(
                $presentation->product,
                $baseQuantity,
                $order->order_date,
            ),
        ];
    }
}
