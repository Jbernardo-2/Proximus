<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryCountItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isBlind = ! $this->resource->relationLoaded('inventoryCount')
            || $this->inventoryCount->isDraft();

        return [
            'id' => $this->id,
            'product' => [
                'id' => $this->product_id,
                'sku' => $this->product_sku,
                'name' => $this->product_name,
                'base_unit_symbol' => $this->base_unit_symbol,
            ],
            'expected_quantity' => $this->when(! $isBlind, $this->expected_quantity),
            'counted_quantity' => $this->counted_quantity,
            'difference' => $this->when(! $isBlind, $this->difference),
            'notes' => $this->notes,
        ];
    }
}
