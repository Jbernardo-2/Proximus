<?php

namespace Database\Factories;

use App\DeliveryRunStatus;
use App\Models\DeliveryRun;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryRun>
 */
class DeliveryRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'run_number' => fake()->unique()->numerify('RUT-2026-######'),
            'client_reference' => null,
            'warehouse_id' => Warehouse::factory(),
            'driver_id' => User::factory()->repartidor(),
            'vehicle_id' => null,
            'scheduled_date' => now()->toDateString(),
            'status' => DeliveryRunStatus::Draft,
            'warehouse_code' => 'BOD-001',
            'warehouse_name' => 'Bodega principal',
            'driver_name' => fake()->name(),
            'vehicle_code' => null,
            'vehicle_license_plate' => null,
            'notes' => null,
            'created_by' => User::factory()->supervisor(),
        ];
    }
}
