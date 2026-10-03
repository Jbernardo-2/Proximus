<?php

namespace Tests\Feature\Web;

use App\Models\Brand;
use App\Models\Category;
use App\Models\MeasurementUnit;
use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_product_form_offers_file_and_camera_sources(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->get(route('products.create'))
            ->assertOk()
            ->assertSee('Buscar en archivos')
            ->assertSee('Usar la cámara')
            ->assertSee('data-image-source="camera"', false)
            ->assertSee('data-product-image-processing-status', false)
            ->assertSee('quitar el fondo dentro del navegador');
    }

    public function test_valid_form_creates_product_image_and_base_presentation(): void
    {
        Storage::fake('public');

        $user = User::factory()->admin()->create();
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();
        $unit = MeasurementUnit::factory()->create();

        $response = $this->actingAs($user)->post('/products', [
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'base_unit_id' => $unit->id,
            'sku' => 'ace-001',
            'name' => 'Aceite vegetal',
            'description' => 'Botella de aceite',
            'image' => UploadedFile::fake()->image('aceite-proximus.jpg', 1200, 1200),
            'allows_decimal' => '0',
            'is_active' => '1',
            'base_presentation_name' => 'Botella',
            'base_barcode' => null,
            'base_sale_price' => '75.00',
            'base_is_sellable' => '1',
            'base_is_purchasable' => '1',
        ]);

        $product = Product::query()->where('sku', 'ACE-001')->firstOrFail();
        $response->assertRedirect(route('products.show', $product));
        $this->assertDatabaseHas('product_presentations', [
            'product_id' => $product->id,
            'name' => 'Botella',
            'is_base' => true,
        ]);
        Storage::disk('public')->assertExists($product->image_path);

        $storedImageSize = getimagesizefromstring(Storage::disk('public')->get($product->image_path));
        $this->assertNotFalse($storedImageSize);
        $this->assertSame(1200, $storedImageSize[0]);
        $this->assertSame(1200, $storedImageSize[1]);
        $this->assertSame('image/jpeg', $storedImageSize['mime']);
    }

    public function test_product_is_not_created_with_a_non_image_upload(): void
    {
        Storage::fake('public');

        $user = User::factory()->admin()->create();
        $category = Category::factory()->create();
        $unit = MeasurementUnit::factory()->create();

        $response = $this->actingAs($user)->post('/products', [
            'category_id' => $category->id,
            'brand_id' => null,
            'base_unit_id' => $unit->id,
            'sku' => 'fail-001',
            'name' => 'Producto sin procesar',
            'description' => null,
            'image' => UploadedFile::fake()->create('producto.pdf', 100, 'application/pdf'),
            'allows_decimal' => '0',
            'is_active' => '1',
            'base_presentation_name' => 'Unidad',
            'base_barcode' => null,
            'base_sale_price' => '10.00',
            'base_is_sellable' => '1',
            'base_is_purchasable' => '1',
        ]);

        $response->assertSessionHasErrors('image');
        $this->assertDatabaseMissing('products', ['sku' => 'FAIL-001']);
    }

    public function test_edit_form_does_not_offer_automatic_background_removal(): void
    {
        $user = User::factory()->admin()->create();
        $product = Product::factory()->create();

        $this->actingAs($user)
            ->get(route('products.edit', $product))
            ->assertOk()
            ->assertSee('data-product-create="false"', false)
            ->assertDontSee('quitar el fondo dentro del navegador');
    }

    public function test_base_presentation_cannot_be_deleted(): void
    {
        $user = User::factory()->admin()->create();
        $product = Product::factory()->create();
        $base = ProductPresentation::factory()->for($product)->base()->create();

        $response = $this->actingAs($user)->delete(
            route('products.presentations.destroy', [$product, $base]),
        );

        $response->assertSessionHas('error', 'La presentación base no se puede eliminar.');
        $this->assertModelExists($base);
    }

    public function test_conversion_preview_rejects_decimal_for_piece_product(): void
    {
        $user = User::factory()->admin()->create();
        $product = Product::factory()->create(['allows_decimal' => false]);
        ProductPresentation::factory()->for($product)->base()->create();

        $this->actingAs($user)
            ->getJson(route('products.conversion-preview', [$product, 'quantity' => '1.5']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity')
            ->assertJsonPath('errors.quantity.0', 'Este producto se maneja en cantidades enteras.');
    }

    public function test_product_detail_renders_presentations_prices_and_conversion_simulator(): void
    {
        $user = User::factory()->admin()->create();
        $product = Product::factory()->create();
        $base = ProductPresentation::factory()->for($product)->base()->create([
            'name' => 'Unidad',
            'sale_price' => '12.50',
        ]);
        $base->priceTiers()->create([
            'min_quantity' => '12',
            'max_quantity' => null,
            'unit_price' => '10.00',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee('Simulador de conversión')
            ->assertSee('Precios por cantidad')
            ->assertSee('12.50')
            ->assertSee('10.00');
    }

    public function test_presentation_configuration_panel_is_not_clipped_and_updates_the_price(): void
    {
        $user = User::factory()->admin()->create();
        $product = Product::factory()->create();
        $presentation = ProductPresentation::factory()->for($product)->base()->create([
            'name' => 'Unidad',
            'sale_price' => '12.50',
        ]);

        $this->actingAs($user)
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('data-presentation-card', false)
            ->assertSee('class="card relative overflow-visible"', false)
            ->assertSee('data-presentation-config-panel', false)
            ->assertSee('sm:top-full', false)
            ->assertSee('Guardar presentación');

        $this->actingAs($user)
            ->put(route('products.presentations.update', [$product, $presentation]), [
                'name' => 'Unidad',
                'barcode' => null,
                'conversion_factor' => '1',
                'sale_price' => '14.75',
                'is_sellable' => '1',
                'is_purchasable' => '1',
                'is_active' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Presentación actualizada correctamente.');

        $this->assertSame('14.7500', $presentation->refresh()->sale_price);
    }
}
