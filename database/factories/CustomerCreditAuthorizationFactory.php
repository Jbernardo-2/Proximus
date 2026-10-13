<?php

namespace Database\Factories;

use App\CustomerCreditAuthorizationType;
use App\Models\Customer;
use App\Models\CustomerCreditAuthorization;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerCreditAuthorization>
 */
class CustomerCreditAuthorizationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'type' => CustomerCreditAuthorizationType::OrderConfirmation,
            'order_id' => Order::factory(),
            'delivery_run_order_id' => null,
            'requested_amount' => '1200.0000',
            'exposure_before' => '4500.0000',
            'credit_limit' => '5000.0000',
            'exceeded_by' => '700.0000',
            'reason' => 'Autorización excepcional de prueba.',
            'authorized_by' => User::factory()->supervisor(),
            'authorized_at' => now(),
        ];
    }
}
