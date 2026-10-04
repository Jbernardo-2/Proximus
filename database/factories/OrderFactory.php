<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\OrderStatus;
use App\PaymentTerm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_number' => fake()->unique()->numerify('PED-2026-######'),
            'client_reference' => null,
            'customer_id' => Customer::factory(),
            'sales_route_id' => null,
            'route_stop_id' => null,
            'salesperson_id' => User::factory()->preventista(),
            'created_by' => User::factory()->supervisor(),
            'order_date' => now()->toDateString(),
            'requested_delivery_date' => null,
            'payment_term' => PaymentTerm::Cash,
            'status' => OrderStatus::Draft,
            'currency' => 'HNL',
            'customer_code' => fake()->unique()->numerify('CLI-####'),
            'customer_name' => fake()->company(),
            'customer_address' => fake()->address(),
            'route_code' => null,
            'route_name' => null,
            'route_visit_day' => null,
            'route_visit_order' => null,
            'salesperson_name' => fake()->name(),
            'notes' => null,
            'subtotal' => '0.0000',
            'total' => '0.0000',
            'confirmed_at' => null,
            'confirmed_by' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
            'cancellation_reason' => null,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (): array => [
            'status' => OrderStatus::Confirmed,
            'confirmed_at' => now(),
            'confirmed_by' => User::factory()->supervisor(),
        ]);
    }
}
