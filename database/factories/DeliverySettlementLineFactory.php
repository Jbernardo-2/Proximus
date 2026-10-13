<?php

namespace Database\Factories;

use App\Models\DeliverySettlement;
use App\Models\DeliverySettlementLine;
use App\SettlementMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliverySettlementLine>
 */
class DeliverySettlementLineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'delivery_settlement_id' => DeliverySettlement::factory(),
            'method' => SettlementMethod::Cash,
            'amount' => '100.0000',
            'reference' => null,
            'delivery_payment_id' => null,
            'customer_receivable_id' => null,
        ];
    }
}
