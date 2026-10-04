<?php

namespace Database\Factories;

use App\DeliveryPaymentStatus;
use App\Models\DeliveryPayment;
use App\Models\DeliveryRunOrder;
use App\Models\User;
use App\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryPayment>
 */
class DeliveryPaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'receipt_number' => fake()->unique()->numerify('REC-2026-######'),
            'client_reference' => null,
            'delivery_run_order_id' => DeliveryRunOrder::factory(),
            'status' => DeliveryPaymentStatus::Active,
            'method' => PaymentMethod::Cash,
            'amount' => '10.0000',
            'reference' => null,
            'notes' => null,
            'received_at' => now(),
            'received_by' => User::factory()->repartidor(),
            'voided_at' => null,
            'voided_by' => null,
            'void_reason' => null,
        ];
    }
}
