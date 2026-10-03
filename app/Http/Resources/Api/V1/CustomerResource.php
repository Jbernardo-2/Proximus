<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'business_name' => $this->business_name,
            'business_type' => $this->business_type,
            'contact_name' => $this->contact_name,
            'phone' => $this->phone,
            'whatsapp' => $this->whatsapp,
            'email' => $this->email,
            'address' => $this->address,
            'reference' => $this->reference,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'route_stops_count' => $this->whenCounted('routeStops'),
            'route_stops' => RouteStopResource::collection($this->whenLoaded('routeStops')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
