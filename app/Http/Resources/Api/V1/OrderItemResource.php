<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'product_id' => $this->product_id,
            'product_presentation_id' => $this->product_presentation_id,
            'price_tier_id' => $this->price_tier_id,
            'product_sku' => $this->product_sku,
            'product_name' => $this->product_name,
            'presentation_name' => $this->presentation_name,
            'base_unit_symbol' => $this->base_unit_symbol,
            'conversion_factor' => $this->conversion_factor,
            'quantity' => $this->quantity,
            'base_quantity' => $this->base_quantity,
            'standard_unit_price' => $this->standard_unit_price,
            'unit_price' => $this->unit_price,
            'price_source' => $this->price_source->value,
            'price_source_label' => $this->price_source->label(),
            'price_overridden_by' => $this->whenLoaded('priceOverriddenBy', fn (): ?array => $this->priceOverriddenBy === null ? null : [
                'id' => $this->priceOverriddenBy->id,
                'name' => $this->priceOverriddenBy->name,
            ]),
            'override_reason' => $this->override_reason,
            'line_total' => $this->line_total,
            'notes' => $this->notes,
            'inventory_reservation' => $this->whenLoaded('inventoryReservation', fn (): ?array => $this->inventoryReservation === null ? null : [
                'id' => $this->inventoryReservation->id,
                'base_quantity' => $this->inventoryReservation->base_quantity,
                'status' => $this->inventoryReservation->status->value,
                'status_label' => $this->inventoryReservation->status->label(),
                'reserved_at' => $this->inventoryReservation->reserved_at?->toISOString(),
                'released_at' => $this->inventoryReservation->released_at?->toISOString(),
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
