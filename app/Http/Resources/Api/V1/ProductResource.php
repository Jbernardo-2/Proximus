<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'image_url' => $this->image_path === null ? null : Storage::disk('public')->url($this->image_path),
            'allows_decimal' => $this->allows_decimal,
            'tracks_lots' => $this->tracks_lots,
            'tracks_expiration' => $this->tracks_expiration,
            'is_active' => $this->is_active,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'brand' => new BrandResource($this->whenLoaded('brand')),
            'base_unit' => new MeasurementUnitResource($this->whenLoaded('baseUnit')),
            'base_presentation' => new ProductPresentationResource($this->whenLoaded('basePresentation')),
            'presentations' => ProductPresentationResource::collection($this->whenLoaded('presentations')),
            'suppliers' => ProductSupplierResource::collection($this->whenLoaded('productSuppliers')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
