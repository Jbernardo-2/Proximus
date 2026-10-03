<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesRouteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'salesperson' => $this->whenLoaded('salesperson', fn (): ?array => $this->salesperson === null ? null : [
                'id' => $this->salesperson->id,
                'name' => $this->salesperson->name,
                'is_active' => $this->salesperson->is_active,
            ]),
            'driver' => $this->whenLoaded('driver', fn (): ?array => $this->driver === null ? null : [
                'id' => $this->driver->id,
                'name' => $this->driver->name,
                'is_active' => $this->driver->is_active,
            ]),
            'is_active' => $this->is_active,
            'stops_count' => $this->whenCounted('stops'),
            'stops' => RouteStopResource::collection($this->whenLoaded('stops')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
