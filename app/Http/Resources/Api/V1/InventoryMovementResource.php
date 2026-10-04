<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'warehouse' => new WarehouseResource($this->whenLoaded('warehouse')),
            'product' => [
                'id' => $this->product_id,
                'sku' => $this->product_sku,
                'name' => $this->product_name,
                'base_unit_symbol' => $this->base_unit_symbol,
            ],
            'presentation' => $this->product_presentation_id === null ? null : [
                'id' => $this->product_presentation_id,
                'name' => $this->presentation_name,
                'quantity' => $this->presentation_quantity,
                'conversion_factor' => $this->conversion_factor,
            ],
            'quantity_on_hand_delta' => $this->quantity_on_hand_delta,
            'quantity_reserved_delta' => $this->quantity_reserved_delta,
            'quantity_on_hand_after' => $this->quantity_on_hand_after,
            'quantity_reserved_after' => $this->quantity_reserved_after,
            'quantity_available_after' => bcsub(
                (string) $this->quantity_on_hand_after,
                (string) $this->quantity_reserved_after,
                6,
            ),
            'reference_number' => $this->reference_number,
            'lot_number' => $this->lot_number,
            'expiration_date' => $this->expiration_date?->toDateString(),
            'reason' => $this->reason,
            'created_by' => $this->whenLoaded('creator', fn (): array => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'occurred_at' => $this->occurred_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
