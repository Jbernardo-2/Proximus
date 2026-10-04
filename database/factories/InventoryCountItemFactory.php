<?php

namespace Database\Factories;

use App\Models\InventoryCount;
use App\Models\InventoryCountItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InventoryCountItem> */
class InventoryCountItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'inventory_count_id' => InventoryCount::factory(),
            'product_id' => Product::factory(),
            'product_sku' => fake()->unique()->bothify('SKU-####'),
            'product_name' => fake()->words(2, true),
            'base_unit_symbol' => 'u',
            'expected_quantity' => '0.000000',
            'counted_quantity' => null,
            'difference' => null,
            'notes' => null,
        ];
    }
}
