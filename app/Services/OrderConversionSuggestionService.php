<?php

namespace App\Services;

use App\Models\Order;

class OrderConversionSuggestionService
{
    public function __construct(private PresentationConversionSuggester $suggester) {}

    /** @return list<array<string, mixed>> */
    public function forOrder(Order $order): array
    {
        $order->loadMissing(['items.product.baseUnit', 'items.product.presentations.priceTiers']);
        $suggestions = [];

        foreach ($order->items->groupBy('product_id') as $items) {
            $baseQuantity = '0';

            foreach ($items as $item) {
                $baseQuantity = bcadd($baseQuantity, $item->base_quantity, 6);
            }

            $product = $items->first()->product;
            $suggestions[] = [
                'product_id' => $product->id,
                'product_sku' => $items->first()->product_sku,
                'product_name' => $items->first()->product_name,
                'current_lines' => $items->map(fn ($item): array => [
                    'presentation_name' => $item->presentation_name,
                    'quantity' => $item->quantity,
                    'base_quantity' => $item->base_quantity,
                ])->values()->all(),
                'suggestion' => $this->suggester->suggest($product, $baseQuantity, $order->order_date),
            ];
        }

        return $suggestions;
    }
}
