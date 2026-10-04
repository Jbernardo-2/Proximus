<?php

namespace Database\Factories;

use App\Models\DeliveryRunItem;
use App\Models\DeliveryRunOrder;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductPresentation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryRunItem>
 */
class DeliveryRunItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'delivery_run_order_id' => DeliveryRunOrder::factory(),
            'order_item_id' => OrderItem::factory(),
            'product_id' => Product::factory(),
            'product_presentation_id' => ProductPresentation::factory(),
            'product_sku' => fake()->unique()->bothify('SKU-####'),
            'product_name' => fake()->words(3, true),
            'presentation_name' => 'Unidad',
            'base_unit_symbol' => 'u',
            'conversion_factor' => '1.000000',
            'unit_price' => '10.0000',
            'requested_quantity' => '1.000000',
            'requested_base_quantity' => '1.000000',
            'prepared_quantity' => '0.000000',
            'prepared_base_quantity' => '0.000000',
            'loaded_quantity' => '0.000000',
            'loaded_base_quantity' => '0.000000',
            'delivered_quantity' => '0.000000',
            'delivered_base_quantity' => '0.000000',
            'returned_quantity' => '0.000000',
            'returned_base_quantity' => '0.000000',
            'damaged_quantity' => '0.000000',
            'damaged_base_quantity' => '0.000000',
            'missing_quantity' => '0.000000',
            'missing_base_quantity' => '0.000000',
            'delivered_line_total' => '0.0000',
        ];
    }
}
