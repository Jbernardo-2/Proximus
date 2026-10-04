<?php

namespace Database\Factories;

use App\InventoryMovementType;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InventoryMovement> */
class InventoryMovementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'warehouse_id' => Warehouse::factory(),
            'product_id' => Product::factory(),
            'product_presentation_id' => null,
            'inventory_document_id' => null,
            'inventory_count_id' => null,
            'order_id' => null,
            'order_item_id' => null,
            'type' => InventoryMovementType::AdjustmentIn,
            'occurred_at' => now(),
            'quantity_on_hand_delta' => '1.000000',
            'quantity_reserved_delta' => '0.000000',
            'quantity_on_hand_after' => '1.000000',
            'quantity_reserved_after' => '0.000000',
            'presentation_quantity' => null,
            'conversion_factor' => null,
            'product_sku' => fake()->unique()->bothify('SKU-####'),
            'product_name' => fake()->words(2, true),
            'presentation_name' => null,
            'base_unit_symbol' => 'u',
            'reference_number' => null,
            'lot_number' => null,
            'expiration_date' => null,
            'reason' => fake()->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
