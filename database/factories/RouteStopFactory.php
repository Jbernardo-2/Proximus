<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\RouteStop;
use App\Models\SalesRoute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RouteStop>
 */
class RouteStopFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sales_route_id' => SalesRoute::factory(),
            'customer_id' => Customer::factory(),
            'visit_day' => fake()->numberBetween(1, 6),
            'visit_order' => fake()->numberBetween(1, 30),
            'notes' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
