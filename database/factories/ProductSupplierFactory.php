<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductSupplier> */
class ProductSupplierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'supplier_id' => Supplier::factory(),
            'product_presentation_id' => ProductPresentation::factory(),
            'supplier_sku' => fake()->optional()->bothify('REF-####'),
            'cost_price' => fake()->randomFloat(2, 1, 250),
            'is_preferred' => false,
            'is_active' => true,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
