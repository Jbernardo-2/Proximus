<?php

namespace App\Http\Resources\Api\V1;

use App\Models\CustomerReceivable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CustomerReceivable */
class CustomerReceivableResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'source' => $this->source->value,
            'document_number' => $this->document_number,
            'order_id' => $this->order_id,
            'original_amount' => $this->original_amount,
            'outstanding_amount' => $this->outstanding_amount,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'issued_at' => $this->issued_at?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'is_overdue' => $this->due_date !== null && $this->due_date->isBefore(today()) && bccomp((string) $this->outstanding_amount, '0', 4) > 0,
            'notes' => $this->notes,
        ];
    }
}
