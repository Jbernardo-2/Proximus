<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryRunOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'visit_order' => $this->visit_order,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'order' => $this->whenLoaded('order', fn (): array => [
                'id' => $this->order->id,
                'order_number' => $this->order->order_number,
                'customer_id' => $this->order->customer_id,
                'customer_code' => $this->order->customer_code,
                'customer_name' => $this->order->customer_name,
                'customer_address' => $this->order->customer_address,
                'route_code' => $this->order->route_code,
                'route_name' => $this->order->route_name,
                'payment_term' => $this->order->payment_term->value,
                'payment_term_label' => $this->order->payment_term->label(),
                'currency' => $this->order->currency,
            ]),
            'requested_total' => $this->requested_total,
            'delivered_total' => $this->delivered_total,
            'collected_total' => $this->collected_total,
            'balance_due' => $this->balance_due,
            'outcome_reason' => $this->outcome_reason?->value,
            'outcome_reason_label' => $this->outcome_reason?->label(),
            'outcome_notes' => $this->outcome_notes,
            'credit_reason' => $this->credit_reason,
            'receiver_name' => $this->receiver_name,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'completed_at' => $this->completed_at?->toISOString(),
            'completed_by' => $this->whenLoaded('completedBy', fn (): ?array => $this->completedBy === null ? null : [
                'id' => $this->completedBy->id,
                'name' => $this->completedBy->name,
            ]),
            'items' => DeliveryRunItemResource::collection($this->whenLoaded('items')),
            'payments' => DeliveryPaymentResource::collection($this->whenLoaded('payments')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
