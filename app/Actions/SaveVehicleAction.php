<?php

namespace App\Actions;

use App\DeliveryRunStatus;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveVehicleAction
{
    /** @param array<string, mixed> $data */
    public function handle(array $data, User $actor, ?Vehicle $vehicle = null): Vehicle
    {
        return DB::transaction(function () use ($data, $actor, $vehicle): Vehicle {
            $savedVehicle = $vehicle === null
                ? new Vehicle(['created_by' => $actor->id])
                : Vehicle::withTrashed()->lockForUpdate()->findOrFail($vehicle->id);

            if (! (bool) $data['is_active'] && $savedVehicle->exists && $savedVehicle->deliveryRuns()
                ->whereIn('status', collect(DeliveryRunStatus::cases())
                    ->filter(fn (DeliveryRunStatus $status): bool => $status->isOpen())
                    ->map->value)
                ->exists()) {
                throw ValidationException::withMessages([
                    'is_active' => ['No puedes desactivar un vehículo asignado a una jornada todavía abierta.'],
                ]);
            }

            $savedVehicle->fill([
                'code' => Str::upper(trim((string) $data['code'])),
                'license_plate' => empty($data['license_plate'])
                    ? null
                    : Str::upper(trim((string) $data['license_plate'])),
                'description' => trim((string) $data['description']),
                'is_active' => (bool) $data['is_active'],
            ])->save();

            return $savedVehicle->refresh();
        });
    }
}
