<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryCountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'count_number' => $this->count_number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'warehouse' => new WarehouseResource($this->whenLoaded('warehouse')),
            'counted_on' => $this->counted_on?->toDateString(),
            'notes' => $this->notes,
            'items_count' => $this->whenCounted('items'),
            'counted_items_count' => $this->when(
                array_key_exists('counted_items_count', $this->resource->getAttributes()),
                $this->counted_items_count,
            ),
            'items' => $this->whenLoaded('items', function () {
                $this->items->each(fn ($item) => $item->setRelation('inventoryCount', $this->resource));

                return InventoryCountItemResource::collection($this->items);
            }),
            'created_by' => $this->whenLoaded('creator', fn (): array => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'posted_at' => $this->posted_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'cancellation_reason' => $this->cancellation_reason,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
