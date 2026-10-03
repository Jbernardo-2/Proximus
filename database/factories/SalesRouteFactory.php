<?php

namespace Database\Factories;

use App\Models\SalesRoute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesRoute>
 */
class SalesRouteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('RUTA-###'),
            'name' => 'Ruta '.fake()->unique()->city(),
            'description' => fake()->optional()->sentence(),
            'salesperson_id' => null,
            'driver_id' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
