<?php

namespace Database\Factories;

use App\InventoryCountStatus;
use App\Models\InventoryCount;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InventoryCount> */
class InventoryCountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'count_number' => fake()->unique()->numerify('CON-2026-######'),
            'warehouse_id' => Warehouse::factory(),
            'status' => InventoryCountStatus::Draft,
            'counted_on' => now()->toDateString(),
            'notes' => null,
            'created_by' => User::factory(),
            'posted_at' => null,
            'posted_by' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
            'cancellation_reason' => null,
        ];
    }
}
