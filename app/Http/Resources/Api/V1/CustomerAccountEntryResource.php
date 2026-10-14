<?php

namespace App\Http\Resources\Api\V1;

use App\Models\CustomerAccountEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CustomerAccountEntry */
class CustomerAccountEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'status' => $this->status->value,
            'receipt_number' => $this->receipt_number,
            'order_id' => $this->order_id,
            'method' => $this->method?->value,
            'debit_amount' => $this->debit_amount,
            'credit_amount' => $this->credit_amount,
            'currency' => $this->currency,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
            'reversal_of_id' => $this->reversal_of_id,
            'reversed_at' => $this->reversed_at?->toIso8601String(),
        ];
    }
}
