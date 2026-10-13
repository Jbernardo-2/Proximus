<?php

namespace Database\Factories;

use App\CustomerAccountEntryStatus;
use App\CustomerAccountEntryType;
use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerAccountEntry>
 */
class CustomerAccountEntryFactory extends Factory
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
            'type' => CustomerAccountEntryType::Sale,
            'status' => CustomerAccountEntryStatus::Active,
            'receipt_number' => null,
            'order_id' => null,
            'delivery_run_order_id' => null,
            'delivery_payment_id' => null,
            'method' => null,
            'debit_amount' => '100.0000',
            'credit_amount' => '0.0000',
            'currency' => 'HNL',
            'reference' => null,
            'notes' => null,
            'receipt_snapshot' => null,
            'occurred_at' => now(),
            'created_by' => User::factory(),
            'reversal_of_id' => null,
            'reversed_at' => null,
            'reversed_by' => null,
        ];
    }
}
