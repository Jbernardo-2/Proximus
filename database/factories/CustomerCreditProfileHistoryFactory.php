<?php

namespace Database\Factories;

use App\CustomerCreditStatus;
use App\Models\Customer;
use App\Models\CustomerCreditProfileHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerCreditProfileHistory>
 */
class CustomerCreditProfileHistoryFactory extends Factory
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
            'from_status' => CustomerCreditStatus::Disabled,
            'to_status' => CustomerCreditStatus::Active,
            'old_credit_limit' => '0.0000',
            'new_credit_limit' => '5000.0000',
            'old_term_days' => 0,
            'new_term_days' => 7,
            'old_grace_days' => 0,
            'new_grace_days' => 0,
            'reason' => 'Aprobación inicial de crédito.',
            'changed_by' => User::factory()->supervisor(),
            'created_at' => now(),
        ];
    }
}
