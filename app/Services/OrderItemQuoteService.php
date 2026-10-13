<?php

namespace App\Services;

use App\Models\InventoryStock;
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
        $availableQuantity = InventoryStock::query()
            ->where('warehouse_id', $order->warehouse_id)
            ->where('product_id', $presentation->product_id)
            ->first()
            ?->availableQuantity() ?? '0.000000';
        $existingOrderQuantity = (string) $order->items()
            ->where('product_id', $presentation->product_id)
            ->sum('base_quantity');
        $orderQuantityAfter = bcadd($existingOrderQuantity, $baseQuantity, 6);
        $projectedAvailableQuantity = bcsub($availableQuantity, $orderQuantityAfter, 6);
        $hasShortage = bccomp($projectedAvailableQuantity, '0', 6) < 0;

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
            'stock' => [
                'available_quantity' => $availableQuantity,
                'existing_order_quantity' => $existingOrderQuantity,
                'requested_base_quantity' => $baseQuantity,
                'order_quantity_after' => $orderQuantityAfter,
                'projected_available_quantity' => $projectedAvailableQuantity,
                'shortage_quantity' => $hasShortage
                    ? bcmul($projectedAvailableQuantity, '-1', 6)
                    : '0.000000',
                'has_shortage' => $hasShortage,
                'base_unit_symbol' => $presentation->product->baseUnit->symbol,
            ],
        ];
    }
}
