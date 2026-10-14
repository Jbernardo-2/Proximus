<?php

namespace Database\Factories;

use App\CustomerVisitType;
use App\Models\Customer;
use App\Models\CustomerVisit;
use App\Models\User;
use App\VisitLocationStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerVisit>
 */
class CustomerVisitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_reference' => null,
            'customer_id' => Customer::factory(),
            'user_id' => User::factory()->preventista(),
            'type' => CustomerVisitType::OrderTaken,
            'subject_type' => null,
            'subject_id' => null,
            'reason' => null,
            'notes' => null,
            'location_status' => VisitLocationStatus::Captured,
            'latitude' => '14.0722750',
            'longitude' => '-87.1921360',
            'accuracy_meters' => '12.0',
            'distance_meters' => 15,
            'occurred_at' => now(),
        ];
    }
}
