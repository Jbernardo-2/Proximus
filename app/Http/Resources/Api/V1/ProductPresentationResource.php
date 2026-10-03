<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductPresentationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'name' => $this->name,
            'barcode' => $this->barcode,
            'conversion_factor' => $this->conversion_factor,
            'sale_price' => $this->sale_price,
            'is_base' => $this->is_base,
            'is_sellable' => $this->is_sellable,
            'is_purchasable' => $this->is_purchasable,
            'is_active' => $this->is_active,
            'price_tiers' => PriceTierResource::collection($this->whenLoaded('priceTiers')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
