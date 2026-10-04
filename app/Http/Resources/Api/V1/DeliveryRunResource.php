<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'run_number' => $this->run_number,
            'client_reference' => $this->client_reference,
            'scheduled_date' => $this->scheduled_date?->toDateString(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'warehouse' => new WarehouseResource($this->whenLoaded('warehouse')),
            'driver' => $this->whenLoaded('driver', fn (): array => [
                'id' => $this->driver->id,
                'name' => $this->driver->name,
            ]),
            'vehicle' => new VehicleResource($this->whenLoaded('vehicle')),
            'warehouse_code' => $this->warehouse_code,
            'warehouse_name' => $this->warehouse_name,
            'driver_name' => $this->driver_name,
            'vehicle_code' => $this->vehicle_code,
            'vehicle_license_plate' => $this->vehicle_license_plate,
            'notes' => $this->notes,
            'orders_count' => $this->whenCounted('runOrders'),
            'orders' => DeliveryRunOrderResource::collection($this->whenLoaded('runOrders')),
            'financials' => [
                'loaded_total' => $this->loaded_total,
                'delivered_total' => $this->delivered_total,
                'collected_total' => $this->collected_total,
                'cash_expected' => $this->cash_expected,
                'cash_declared' => $this->cash_declared,
                'cash_difference' => $this->cash_difference,
                'transfer_total' => $this->transfer_total,
                'card_total' => $this->card_total,
                'check_total' => $this->check_total,
                'other_payment_total' => $this->other_payment_total,
                'credit_total' => $this->credit_total,
            ],
            'preparation_started_at' => $this->preparation_started_at?->toISOString(),
            'loaded_at' => $this->loaded_at?->toISOString(),
            'departed_at' => $this->departed_at?->toISOString(),
            'settled_at' => $this->settled_at?->toISOString(),
            'settlement_notes' => $this->settlement_notes,
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'cancellation_reason' => $this->cancellation_reason,
            'status_history' => $this->whenLoaded('statusHistory', fn () => $this->statusHistory->map(fn ($event): array => [
                'id' => $event->id,
                'from_status' => $event->from_status?->value,
                'to_status' => $event->to_status->value,
                'to_status_label' => $event->to_status->label(),
                'changed_by' => $event->changedBy === null ? null : [
                    'id' => $event->changedBy->id,
                    'name' => $event->changedBy->name,
                ],
                'reason' => $event->reason,
                'created_at' => $event->created_at?->toISOString(),
            ])),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
