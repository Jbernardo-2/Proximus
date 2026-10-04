<?php

namespace App\Http\Resources\Api\V1;

use App\Weekday;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'client_reference' => $this->client_reference,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'payment_term' => $this->payment_term->value,
            'payment_term_label' => $this->payment_term->label(),
            'order_date' => $this->order_date?->toDateString(),
            'requested_delivery_date' => $this->requested_delivery_date?->toDateString(),
            'currency' => $this->currency,
            'customer' => [
                'id' => $this->customer_id,
                'code' => $this->customer_code,
                'business_name' => $this->customer_name,
                'address' => $this->customer_address,
            ],
            'route' => $this->sales_route_id === null ? null : [
                'id' => $this->sales_route_id,
                'code' => $this->route_code,
                'name' => $this->route_name,
                'route_stop_id' => $this->route_stop_id,
                'visit_day' => $this->route_visit_day,
                'visit_day_label' => $this->route_visit_day === null
                    ? null
                    : Weekday::tryFrom((int) $this->route_visit_day)?->label(),
                'visit_order' => $this->route_visit_order,
            ],
            'salesperson' => [
                'id' => $this->salesperson_id,
                'name' => $this->salesperson_name,
            ],
            'created_by' => $this->whenLoaded('creator', fn (): array => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'notes' => $this->notes,
            'subtotal' => $this->subtotal,
            'total' => $this->total,
            'items_count' => $this->whenCounted('items'),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'confirmed_at' => $this->confirmed_at?->toISOString(),
            'confirmed_by' => $this->whenLoaded('confirmedBy', fn (): ?array => $this->confirmedBy === null ? null : [
                'id' => $this->confirmedBy->id,
                'name' => $this->confirmedBy->name,
            ]),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'cancelled_by' => $this->whenLoaded('cancelledBy', fn (): ?array => $this->cancelledBy === null ? null : [
                'id' => $this->cancelledBy->id,
                'name' => $this->cancelledBy->name,
            ]),
            'cancellation_reason' => $this->cancellation_reason,
            'status_history' => OrderStatusHistoryResource::collection($this->whenLoaded('statusHistory')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
