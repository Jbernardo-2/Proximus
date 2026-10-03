<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Category;
use App\Models\MeasurementUnit;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(3, true));

        return [
            'category_id' => Category::factory(),
            'brand_id' => Brand::factory(),
            'base_unit_id' => MeasurementUnit::factory(),
            'sku' => fake()->unique()->bothify('SKU-####'),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'description' => fake()->optional()->sentence(),
            'image_path' => null,
            'allows_decimal' => false,
            'is_active' => true,
        ];
    }
}
