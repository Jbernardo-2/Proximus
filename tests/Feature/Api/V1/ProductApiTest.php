<?php

namespace Tests\Feature\Api\V1;

use App\Models\Brand;
use App\Models\Category;
use App\Models\MeasurementUnit;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_valid_payload_creates_product_and_base_presentation_and_returns_201(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create(), ['catalog:manage']);
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();
        $unit = MeasurementUnit::factory()->create([
            'name' => 'Unidad',
            'symbol' => 'u',
        ]);

        $response = $this->postJson('/api/v1/products', [
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'base_unit_id' => $unit->id,
            'sku' => 'gal-001',
            'name' => 'Galleta vainilla',
            'description' => 'Presentación individual',
            'allows_decimal' => false,
            'is_active' => true,
            'base_presentation_name' => 'Unidad',
            'base_barcode' => '750000000001',
            'base_sale_price' => '12.50',
            'base_is_sellable' => true,
            'base_is_purchasable' => true,
            'image_path' => 'no-debe-aceptarse.php',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.sku', 'GAL-001')
            ->assertJsonPath('data.presentations.0.is_base', true)
            ->assertJsonPath('data.presentations.0.sale_price', '12.5000');
        $this->assertDatabaseHas('products', [
            'sku' => 'GAL-001',
            'name' => 'Galleta vainilla',
            'image_path' => null,
        ]);
        $this->assertDatabaseHas('product_presentations', [
            'name' => 'Unidad',
            'barcode' => '750000000001',
            'is_base' => true,
        ]);
    }

    public function test_conversion_preview_returns_box_and_units_without_changing_data(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create(), ['catalog:manage']);
        [$product, $base] = $this->createProductWithBasePresentation();
        $box = ProductPresentation::factory()->for($product)->create([
            'name' => 'Caja 24',
            'conversion_factor' => '24',
            'sale_price' => '240',
            'is_base' => false,
        ]);

        $response = $this->getJson("/api/v1/products/{$product->id}/conversion-preview?quantity=30");

        $response
            ->assertOk()
            ->assertJsonPath('data.requested_base_quantity', '30')
            ->assertJsonPath('data.components.0.presentation_id', $box->id)
            ->assertJsonPath('data.components.0.count', '1')
            ->assertJsonPath('data.components.1.presentation_id', $base->id)
            ->assertJsonPath('data.components.1.count', '6')
            ->assertJsonPath('data.is_exact', true)
            ->assertJsonPath('data.estimated_total', '312.0000');
        $this->assertDatabaseCount('product_presentations', 2);
    }

    public function test_overlapping_active_price_range_returns_422(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create(), ['catalog:manage']);
        [$product, $presentation] = $this->createProductWithBasePresentation();
        PriceTier::factory()->for($presentation, 'presentation')->create([
            'min_quantity' => '10',
            'max_quantity' => '20',
            'unit_price' => '9',
        ]);

        $response = $this->postJson(
            "/api/v1/products/{$product->id}/presentations/{$presentation->id}/price-tiers",
            [
                'min_quantity' => '15',
                'max_quantity' => '30',
                'unit_price' => '8.50',
                'starts_at' => null,
                'ends_at' => null,
                'is_active' => true,
            ],
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('min_quantity')
            ->assertJsonPath('errors.min_quantity.0', 'El rango de cantidades y vigencia se cruza con otro precio activo de esta presentación.');
        $this->assertDatabaseCount('price_tiers', 1);
    }

    public function test_scoped_binding_returns_404_for_presentation_of_another_product(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create(), ['catalog:manage']);
        [$firstProduct] = $this->createProductWithBasePresentation();
        [$secondProduct, $secondPresentation] = $this->createProductWithBasePresentation();

        $this->putJson(
            "/api/v1/products/{$firstProduct->id}/presentations/{$secondPresentation->id}",
            [
                'name' => 'Unidad alterada',
                'barcode' => null,
                'conversion_factor' => '1',
                'sale_price' => '20',
                'is_sellable' => true,
                'is_purchasable' => true,
                'is_active' => true,
            ],
        )->assertNotFound();

        $this->assertDatabaseHas('product_presentations', [
            'id' => $secondPresentation->id,
            'product_id' => $secondProduct->id,
            'name' => 'Unidad',
        ]);
    }

    /** @return array{Product, ProductPresentation} */
    private function createProductWithBasePresentation(): array
    {
        $unit = MeasurementUnit::factory()->create();
        $product = Product::factory()->create([
            'base_unit_id' => $unit->id,
            'allows_decimal' => false,
        ]);
        $base = ProductPresentation::factory()->for($product)->base()->create([
            'sale_price' => '12',
        ]);

        return [$product, $base];
    }
}
