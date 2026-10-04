<?php

namespace Database\Factories;

use App\DeliveryOrderStatus;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunOrder;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryRunOrder>
 */
class DeliveryRunOrderFactory extends Factory
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
            'order_id' => Order::factory()->confirmed(),
            'visit_order' => fake()->numberBetween(1, 100),
            'status' => DeliveryOrderStatus::Pending,
            'requested_total' => '100.0000',
            'delivered_total' => '0.0000',
            'collected_total' => '0.0000',
            'balance_due' => '0.0000',
            'outcome_reason' => null,
            'outcome_notes' => null,
            'credit_reason' => null,
            'receiver_name' => null,
            'latitude' => null,
            'longitude' => null,
            'completed_at' => null,
            'completed_by' => null,
        ];
    }
}
