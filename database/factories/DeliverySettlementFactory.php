<?php

namespace Database\Factories;

use App\Models\DeliveryRunOrder;
use App\Models\DeliverySettlement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliverySettlement>
 */
class DeliverySettlementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'delivery_run_order_id' => DeliveryRunOrder::factory(),
            'total_amount' => '100.0000',
            'currency' => 'HNL',
            'settled_at' => now(),
            'settled_by' => User::factory()->repartidor(),
        ];
    }
}
