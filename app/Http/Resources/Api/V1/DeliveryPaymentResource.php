<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'receipt_number' => $this->receipt_number,
            'client_reference' => $this->client_reference,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'method' => $this->method->value,
            'method_label' => $this->method->label(),
            'amount' => $this->amount,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'received_at' => $this->received_at?->toISOString(),
            'received_by' => $this->whenLoaded('receivedBy', fn (): array => [
                'id' => $this->receivedBy->id,
                'name' => $this->receivedBy->name,
            ]),
            'voided_at' => $this->voided_at?->toISOString(),
            'void_reason' => $this->void_reason,
            'voided_by' => $this->whenLoaded('voidedBy', fn (): ?array => $this->voidedBy === null ? null : [
                'id' => $this->voidedBy->id,
                'name' => $this->voidedBy->name,
            ]),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
