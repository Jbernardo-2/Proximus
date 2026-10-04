<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_number' => $this->document_number,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'warehouse' => new WarehouseResource($this->whenLoaded('warehouse')),
            'supplier' => $this->whenLoaded('supplier', fn (): ?array => $this->supplier === null ? null : [
                'id' => $this->supplier->id,
                'code' => $this->supplier->code,
                'name' => $this->supplier->name,
            ]),
            'occurred_on' => $this->occurred_on?->toDateString(),
            'external_reference' => $this->external_reference,
            'notes' => $this->notes,
            'items_count' => $this->whenCounted('items'),
            'items' => InventoryDocumentItemResource::collection($this->whenLoaded('items')),
            'created_by' => $this->whenLoaded('creator', fn (): array => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'posted_at' => $this->posted_at?->toISOString(),
            'posted_by' => $this->whenLoaded('postedBy', fn (): ?array => $this->postedBy === null ? null : [
                'id' => $this->postedBy->id,
                'name' => $this->postedBy->name,
            ]),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'cancellation_reason' => $this->cancellation_reason,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
