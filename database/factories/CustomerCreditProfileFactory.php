<?php

namespace Database\Factories;

use App\CustomerCreditStatus;
use App\Models\Customer;
use App\Models\CustomerCreditProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerCreditProfile>
 */
class CustomerCreditProfileFactory extends Factory
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
            'status' => CustomerCreditStatus::Active,
            'credit_limit' => '5000.0000',
            'term_days' => 7,
            'grace_days' => 0,
            'notes' => null,
            'approved_at' => now(),
            'approved_by' => User::factory()->supervisor(),
            'updated_by' => User::factory()->supervisor(),
        ];
    }
}
