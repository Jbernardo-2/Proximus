<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('VEH-###'),
            'license_plate' => fake()->unique()->bothify('???-####'),
            'description' => fake()->randomElement(['Camión', 'Panel', 'Pickup']).' '.fake()->unique()->numerify('##'),
            'is_active' => true,
            'created_by' => User::factory()->supervisor(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
