<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryStockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'warehouse' => new WarehouseResource($this->whenLoaded('warehouse')),
            'product' => $this->whenLoaded('product', fn (): array => [
                'id' => $this->product->id,
                'sku' => $this->product->sku,
                'name' => $this->product->name,
                'base_unit' => [
                    'id' => $this->product->baseUnit->id,
                    'name' => $this->product->baseUnit->name,
                    'symbol' => $this->product->baseUnit->symbol,
                ],
                'tracks_lots' => $this->product->tracks_lots,
                'tracks_expiration' => $this->product->tracks_expiration,
            ]),
            'quantity_on_hand' => $this->quantity_on_hand,
            'quantity_reserved' => $this->quantity_reserved,
            'quantity_available' => $this->availableQuantity(),
            'shortage_quantity' => $this->shortageQuantity(),
            'reorder_point' => $this->reorder_point,
            'is_low_stock' => $this->isLowStock(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
