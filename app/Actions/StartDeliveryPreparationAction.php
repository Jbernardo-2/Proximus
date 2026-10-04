<?php

namespace App\Actions;

use App\DeliveryRunStatus;
use App\Models\DeliveryRun;
use App\Models\User;
use App\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StartDeliveryPreparationAction
{
    public function handle(DeliveryRun $deliveryRun, User $actor): DeliveryRun
    {
        return DB::transaction(function () use ($deliveryRun, $actor): DeliveryRun {
            $run = DeliveryRun::query()
                ->with(['warehouse', 'driver', 'vehicle'])
                ->lockForUpdate()
                ->findOrFail($deliveryRun->id);

            if ($run->status !== DeliveryRunStatus::Draft) {
                throw ValidationException::withMessages([
                    'delivery_run' => ['Solo una jornada en borrador puede iniciar preparación.'],
                ]);
            }

            if (! $run->runOrders()->exists()) {
                throw ValidationException::withMessages([
                    'orders' => ['Asigna al menos un pedido antes de iniciar la preparación.'],
                ]);
            }

            if (! $run->warehouse->is_active) {
                throw ValidationException::withMessages([
                    'warehouse_id' => ['La bodega de la jornada ya no está activa.'],
                ]);
            }

            if (! $run->driver->is_active || $run->driver->role !== UserRole::Repartidor) {
                throw ValidationException::withMessages([
                    'driver_id' => ['El repartidor de la jornada ya no está disponible.'],
                ]);
            }

            if ($run->vehicle !== null && ! $run->vehicle->is_active) {
                throw ValidationException::withMessages([
                    'vehicle_id' => ['El vehículo de la jornada ya no está activo.'],
                ]);
            }

            $run->forceFill([
                'status' => DeliveryRunStatus::Preparing,
                'preparation_started_at' => now(),
                'preparation_started_by' => $actor->id,
            ])->save();
            $run->statusHistory()->create([
                'from_status' => DeliveryRunStatus::Draft,
                'to_status' => DeliveryRunStatus::Preparing,
                'changed_by' => $actor->id,
                'reason' => 'Bodega inició la preparación de los pedidos asignados.',
            ]);

            return $run;
        });
    }
}
