<?php

namespace App\Actions;

use App\DeliveryRunStatus;
use App\Models\DeliveryRun;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateDeliveryRunAction
{
    public function __construct(private GenerateDeliveryNumberAction $generateNumber) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data, User $actor): DeliveryRun
    {
        return DB::transaction(function () use ($data, $actor): DeliveryRun {
            $warehouse = Warehouse::query()->active()->lockForUpdate()->findOrFail($data['warehouse_id']);
            $driver = User::query()->where('is_active', true)->lockForUpdate()->findOrFail($data['driver_id']);

            if ($driver->role !== UserRole::Repartidor) {
                throw ValidationException::withMessages([
                    'driver_id' => ['El usuario seleccionado no tiene el rol de repartidor.'],
                ]);
            }

            $vehicle = empty($data['vehicle_id'])
                ? null
                : Vehicle::query()->active()->lockForUpdate()->findOrFail($data['vehicle_id']);
            $scheduledDate = CarbonImmutable::createFromFormat('!Y-m-d', (string) $data['scheduled_date']);

            if ($scheduledDate === false) {
                throw ValidationException::withMessages([
                    'scheduled_date' => ['La fecha programada no es válida.'],
                ]);
            }

            $run = DeliveryRun::query()->create([
                'run_number' => $this->generateNumber->handle('run', 'RUT', $scheduledDate),
                'client_reference' => $data['client_reference'] ?? null,
                'warehouse_id' => $warehouse->id,
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle?->id,
                'scheduled_date' => $scheduledDate->toDateString(),
                'status' => DeliveryRunStatus::Draft,
                'warehouse_code' => $warehouse->code,
                'warehouse_name' => $warehouse->name,
                'driver_name' => $driver->name,
                'vehicle_code' => $vehicle?->code,
                'vehicle_license_plate' => $vehicle?->license_plate,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);
            $run->statusHistory()->create([
                'from_status' => null,
                'to_status' => DeliveryRunStatus::Draft,
                'changed_by' => $actor->id,
                'reason' => 'Jornada creada para asignar pedidos confirmados.',
            ]);

            return $run;
        });
    }
}
