<?php

namespace Database\Factories;

use App\InventoryReservationStatus;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InventoryReservation> */
class InventoryReservationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'order_item_id' => OrderItem::factory(),
            'warehouse_id' => Warehouse::factory(),
            'product_id' => Product::factory(),
            'base_quantity' => '1.000000',
            'status' => InventoryReservationStatus::Active,
            'reserved_at' => now(),
            'released_at' => null,
            'created_by' => User::factory(),
            'released_by' => null,
        ];
    }
}
