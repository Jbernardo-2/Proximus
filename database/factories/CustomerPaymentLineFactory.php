<?php

namespace Database\Factories;

use App\Models\CustomerAccountEntry;
use App\Models\CustomerPaymentLine;
use App\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerPaymentLine>
 */
class CustomerPaymentLineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_account_entry_id' => CustomerAccountEntry::factory(),
            'method' => PaymentMethod::Cash,
            'amount' => '100.0000',
            'reference' => null,
        ];
    }
}
