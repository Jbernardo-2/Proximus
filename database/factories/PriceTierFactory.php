<?php

namespace Database\Factories;

use App\Models\PriceTier;
use App\Models\ProductPresentation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PriceTier> */
class PriceTierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_presentation_id' => ProductPresentation::factory(),
            'min_quantity' => '12',
            'max_quantity' => null,
            'unit_price' => fake()->randomFloat(2, 1, 300),
            'starts_at' => null,
            'ends_at' => null,
            'is_active' => true,
        ];
    }
}
