<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryDocumentItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product' => [
                'id' => $this->product_id,
                'sku' => $this->product_sku,
                'name' => $this->product_name,
                'base_unit_symbol' => $this->base_unit_symbol,
            ],
            'presentation' => [
                'id' => $this->product_presentation_id,
                'name' => $this->presentation_name,
                'conversion_factor' => $this->conversion_factor,
            ],
            'quantity' => $this->quantity,
            'base_quantity' => $this->base_quantity,
            'unit_cost' => $this->unit_cost,
            'lot_number' => $this->lot_number,
            'expiration_date' => $this->expiration_date?->toDateString(),
            'notes' => $this->notes,
        ];
    }
}
