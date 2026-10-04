<?php

namespace Database\Factories;

use App\DeliveryRunStatus;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunStatusHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryRunStatusHistory>
 */
class DeliveryRunStatusHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'delivery_run_id' => DeliveryRun::factory(),
            'from_status' => null,
            'to_status' => DeliveryRunStatus::Draft,
            'changed_by' => User::factory()->supervisor(),
            'reason' => fake()->sentence(),
        ];
    }
}
