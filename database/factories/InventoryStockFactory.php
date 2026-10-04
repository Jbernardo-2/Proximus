<?php

namespace Database\Factories;

use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InventoryStock> */
class InventoryStockFactory extends Factory
{
    public function definition(): array
    {
        return [
            'warehouse_id' => Warehouse::factory(),
            'product_id' => Product::factory(),
            'quantity_on_hand' => '0.000000',
            'quantity_reserved' => '0.000000',
            'reorder_point' => '0.000000',
        ];
    }
}
