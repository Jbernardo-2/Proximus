<?php

namespace Database\Factories;

use App\CustomerReceivableSource;
use App\CustomerReceivableStatus;
use App\Models\Customer;
use App\Models\CustomerReceivable;
use App\Models\DeliveryRunOrder;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerReceivable>
 */
class CustomerReceivableFactory extends Factory
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
            'source' => CustomerReceivableSource::Sale,
            'order_id' => Order::factory(),
            'delivery_run_order_id' => DeliveryRunOrder::factory(),
            'original_amount' => '100.0000',
            'outstanding_amount' => '100.0000',
            'currency' => 'HNL',
            'status' => CustomerReceivableStatus::Open,
            'opened_at' => now(),
            'issued_at' => today(),
            'due_date' => today()->addDays(7),
            'term_days' => 7,
            'document_number' => null,
            'notes' => null,
            'paid_at' => null,
            'voided_at' => null,
            'voided_by' => null,
            'void_reason' => null,
            'created_by' => User::factory(),
        ];
    }
}
