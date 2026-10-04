<?php

namespace App\Actions;

use App\Models\DeliveryRun;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateDeliveryRunAction
{
    /** @param array<string, mixed> $data */
    public function handle(DeliveryRun $deliveryRun, array $data): DeliveryRun
    {
        return DB::transaction(function () use ($deliveryRun, $data): DeliveryRun {
            $lockedRun = DeliveryRun::query()->lockForUpdate()->findOrFail($deliveryRun->id);

            if (! $lockedRun->isDraft()) {
                throw ValidationException::withMessages([
                    'delivery_run' => ['Solo se puede editar una jornada en borrador.'],
                ]);
            }

            $warehouse = Warehouse::query()->active()->findOrFail($data['warehouse_id']);

            if ($lockedRun->warehouse_id !== $warehouse->id && $lockedRun->runOrders()->exists()) {
                throw ValidationException::withMessages([
                    'warehouse_id' => ['Retira los pedidos asignados antes de cambiar la bodega.'],
                ]);
            }

            $driver = User::query()->where('is_active', true)->findOrFail($data['driver_id']);

            if ($driver->role !== UserRole::Repartidor) {
                throw ValidationException::withMessages([
                    'driver_id' => ['El usuario seleccionado no tiene el rol de repartidor.'],
                ]);
            }

            $vehicle = empty($data['vehicle_id'])
                ? null
                : Vehicle::query()->active()->findOrFail($data['vehicle_id']);

            $lockedRun->forceFill([
                'client_reference' => $data['client_reference'] ?? null,
                'warehouse_id' => $warehouse->id,
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle?->id,
                'scheduled_date' => $data['scheduled_date'],
                'warehouse_code' => $warehouse->code,
                'warehouse_name' => $warehouse->name,
                'driver_name' => $driver->name,
                'vehicle_code' => $vehicle?->code,
                'vehicle_license_plate' => $vehicle?->license_plate,
                'notes' => $data['notes'] ?? null,
            ])->save();

            return $lockedRun->refresh();
        });
    }
}
