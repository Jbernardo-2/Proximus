<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\OrderStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderStatusHistory>
 */
class OrderStatusHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'from_status' => null,
            'to_status' => OrderStatus::Draft,
            'changed_by' => User::factory()->supervisor(),
            'reason' => 'Pedido creado.',
        ];
    }
}
