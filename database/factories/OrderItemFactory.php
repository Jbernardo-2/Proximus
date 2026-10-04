<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductPresentation;
use App\OrderPriceSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'product_presentation_id' => ProductPresentation::factory(),
            'price_tier_id' => null,
            'product_sku' => fake()->unique()->bothify('SKU-####'),
            'product_name' => fake()->words(3, true),
            'presentation_name' => 'Unidad',
            'base_unit_symbol' => 'ud',
            'conversion_factor' => '1.000000',
            'quantity' => '1.000000',
            'base_quantity' => '1.000000',
            'standard_unit_price' => '10.0000',
            'unit_price' => '10.0000',
            'price_source' => OrderPriceSource::Presentation,
            'price_overridden_by' => null,
            'override_reason' => null,
            'line_total' => '10.0000',
            'notes' => null,
        ];
    }
}
