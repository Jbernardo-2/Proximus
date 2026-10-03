<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductSupplierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'supplier_id' => $this->supplier_id,
            'product_presentation_id' => $this->product_presentation_id,
            'supplier_sku' => $this->supplier_sku,
            'cost_price' => $this->cost_price,
            'is_preferred' => $this->is_preferred,
            'is_active' => $this->is_active,
            'notes' => $this->notes,
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'presentation' => new ProductPresentationResource($this->whenLoaded('presentation')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
