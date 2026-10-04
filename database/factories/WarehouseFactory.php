<?php

namespace Database\Factories;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Warehouse> */
class WarehouseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('BOD-TEST-####-????'),
            'name' => fake()->unique()->company().' Bodega',
            'address' => fake()->optional()->address(),
            'is_default' => false,
            'is_active' => true,
            'created_by' => null,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (): array => ['is_default' => true]);
    }
}
