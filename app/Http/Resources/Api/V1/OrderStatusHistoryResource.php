<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderStatusHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'from_status' => $this->from_status?->value,
            'from_status_label' => $this->from_status?->label(),
            'to_status' => $this->to_status->value,
            'to_status_label' => $this->to_status->label(),
            'changed_by' => $this->whenLoaded('changedBy', fn (): array => [
                'id' => $this->changedBy->id,
                'name' => $this->changedBy->name,
            ]),
            'reason' => $this->reason,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
