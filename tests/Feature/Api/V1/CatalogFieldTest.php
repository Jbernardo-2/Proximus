<?php

namespace Tests\Feature\Api\V1;

use App\Models\MeasurementUnit;
use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** The photo and the barcodes a supervisor fixes from the phone. */
class CatalogFieldTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_supervisor_replaces_a_product_photo_and_the_old_one_is_removed(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/old.jpg', 'old');
        [$product] = $this->product();
        $product->update(['image_path' => 'products/old.jpg']);
        $keeper = User::factory()->supervisor()->create();
        Sanctum::actingAs($keeper, $keeper->apiAbilities());

        $response = $this->post("/api/v1/products/{$product->id}/photo", ['image' => UploadedFile::fake()->image('estante.jpg', 1200, 900)], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.id', $product->id);

        $path = $product->refresh()->image_path;
        $this->assertNotSame('products/old.jpg', $path);
        Storage::disk('public')->assertExists($path);
        Storage::disk('public')->assertMissing('products/old.jpg');
        $this->assertNotNull($response->json('data.image_version'));
        $this->post("/api/v1/products/{$product->id}/photo", ['image' => UploadedFile::fake()->create('nota.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertUnprocessable();

        // On the phone the products belong to supervisors and admins; the keeper edits them on the web.
        foreach ([User::factory()->preventista()->create(), User::factory()->bodeguero()->create()] as $other) {
            Sanctum::actingAs($other, $other->apiAbilities());
            $this->post("/api/v1/products/{$product->id}/photo", ['image' => UploadedFile::fake()->image('x.jpg')], ['Accept' => 'application/json'])->assertForbidden();
        }
    }

    public function test_a_supervisor_assigns_a_barcode_and_learns_whose_it_is_when_it_is_taken(): void
    {
        [$product, $presentation] = $this->product();
        [$other, $otherPresentation] = $this->product('Frijol rojo');
        $otherPresentation->update(['barcode' => '7400000000999']);
        $product->forceFill(['updated_at' => now()->subDay()])->saveQuietly();
        $keeper = User::factory()->supervisor()->create();
        Sanctum::actingAs($keeper, $keeper->apiAbilities());
        $url = "/api/v1/products/{$product->id}/presentations/{$presentation->id}/barcode";

        $this->putJson($url, ['barcode' => ' 7400000000123 '])->assertOk()->assertJsonPath('data.barcode', '7400000000123');
        $this->assertTrue($product->refresh()->updated_at->isToday(), 'the phones download the change');
        $this->putJson($url, ['barcode' => '7400000000999'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.barcode.0', "Ese código ya es de {$other->name} · {$otherPresentation->name}.");
        $this->putJson($url, ['barcode' => null])->assertOk();
        $this->assertNull($presentation->refresh()->barcode);
        $this->putJson("/api/v1/products/{$other->id}/presentations/{$presentation->id}/barcode", ['barcode' => '1'])->assertNotFound();
    }

    public function test_on_the_phone_only_supervisors_and_admins_create_products(): void
    {
        [$product] = $this->product();
        $payload = [
            'name' => 'Frijol nuevo',
            'sku' => '7409999000111',
            'category_id' => $product->category_id,
            'base_unit_id' => $product->base_unit_id,
            'allows_decimal' => false,
            'tracks_lots' => false,
            'tracks_expiration' => false,
            'is_active' => true,
            'base_presentation_name' => 'Unidad',
            'base_barcode' => '7409999000111',
            'base_sale_price' => '25.50',
            'base_is_sellable' => true,
            'base_is_purchasable' => true,
        ];

        $keeper = User::factory()->bodeguero()->create();
        Sanctum::actingAs($keeper, $keeper->apiAbilities());
        $this->postJson('/api/v1/products', $payload)->assertForbidden();

        $supervisor = User::factory()->supervisor()->create();
        Sanctum::actingAs($supervisor, $supervisor->apiAbilities());
        $this->postJson('/api/v1/products', $payload)
            ->assertCreated()
            ->assertJsonPath('data.sku', '7409999000111')
            ->assertJsonPath('data.presentations.0.barcode', '7409999000111');
    }

    /** @return array{0: Product, 1: ProductPresentation} */
    private function product(string $name = 'Arroz 1 lb'): array
    {
        $unit = MeasurementUnit::factory()->create(['name' => fake()->unique()->word(), 'symbol' => fake()->unique()->lexify('u??')]);
        $product = Product::factory()->for($unit, 'baseUnit')->create(['name' => $name]);

        return [$product, ProductPresentation::factory()->for($product)->base()->create(['name' => 'Unidad', 'barcode' => null])];
    }
}
