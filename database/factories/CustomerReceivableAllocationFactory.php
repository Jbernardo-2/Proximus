<?php

namespace Database\Factories;

use App\Models\CustomerAccountEntry;
use App\Models\CustomerReceivable;
use App\Models\CustomerReceivableAllocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerReceivableAllocation>
 */
class CustomerReceivableAllocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_receivable_id' => CustomerReceivable::factory(),
            'customer_account_entry_id' => CustomerAccountEntry::factory(),
            'amount' => '10.0000',
        ];
    }
}
