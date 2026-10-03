<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductPresentation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductPresentation> */
class ProductPresentationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => fake()->randomElement(['Unidad', 'Paquete', 'Caja', 'Fardo']).' '.fake()->unique()->numerify('##'),
            'barcode' => null,
            'conversion_factor' => fake()->randomElement(['1', '6', '12', '24']),
            'sale_price' => fake()->randomFloat(2, 1, 500),
            'is_base' => false,
            'is_sellable' => true,
            'is_purchasable' => true,
            'is_active' => true,
        ];
    }

    public function base(): static
    {
        return $this->state(fn (): array => [
            'name' => 'Unidad',
            'conversion_factor' => '1',
            'is_base' => true,
        ]);
    }
}
