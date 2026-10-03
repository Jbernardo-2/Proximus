<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RouteStopResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sales_route_id' => $this->sales_route_id,
            'customer_id' => $this->customer_id,
            'customer' => $this->whenLoaded('customer', fn (): ?array => $this->customer === null ? null : [
                'id' => $this->customer->id,
                'code' => $this->customer->code,
                'business_name' => $this->customer->business_name,
                'address' => $this->customer->address,
                'is_active' => $this->customer->is_active,
            ]),
            'sales_route' => $this->whenLoaded('salesRoute', fn (): ?array => $this->salesRoute === null ? null : [
                'id' => $this->salesRoute->id,
                'code' => $this->salesRoute->code,
                'name' => $this->salesRoute->name,
                'is_active' => $this->salesRoute->is_active,
            ]),
            'visit_day' => $this->visit_day->value,
            'visit_day_label' => $this->visit_day->label(),
            'visit_order' => $this->visit_order,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
