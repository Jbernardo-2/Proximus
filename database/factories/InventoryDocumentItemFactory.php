<?php

namespace Database\Factories;

use App\Models\InventoryDocument;
use App\Models\InventoryDocumentItem;
use App\Models\Product;
use App\Models\ProductPresentation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InventoryDocumentItem> */
class InventoryDocumentItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'inventory_document_id' => InventoryDocument::factory(),
            'product_id' => Product::factory(),
            'product_presentation_id' => fn (array $attributes) => ProductPresentation::factory()
                ->create(['product_id' => $attributes['product_id']])->id,
            'product_sku' => fake()->unique()->bothify('SKU-####'),
            'product_name' => fake()->words(2, true),
            'presentation_name' => 'Unidad',
            'base_unit_symbol' => 'u',
            'conversion_factor' => '1.000000',
            'quantity' => '1.000000',
            'base_quantity' => '1.000000',
            'unit_cost' => null,
            'lot_number' => null,
            'expiration_date' => null,
            'notes' => null,
        ];
    }
}
