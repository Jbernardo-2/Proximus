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

/** The photo and the barcodes a keeper fixes from the phone. */
class CatalogFieldTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_keeper_replaces_a_product_photo_and_the_old_one_is_removed(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/old.jpg', 'old');
        [$product] = $this->product();
        $product->update(['image_path' => 'products/old.jpg']);
        $keeper = User::factory()->bodeguero()->create();
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

        $seller = User::factory()->preventista()->create();
        Sanctum::actingAs($seller, $seller->apiAbilities());
        $this->post("/api/v1/products/{$product->id}/photo", ['image' => UploadedFile::fake()->image('x.jpg')], ['Accept' => 'application/json'])->assertForbidden();
    }

    public function test_a_keeper_assigns_a_barcode_and_learns_whose_it_is_when_it_is_taken(): void
    {
        [$product, $presentation] = $this->product();
        [$other, $otherPresentation] = $this->product('Frijol rojo');
        $otherPresentation->update(['barcode' => '7400000000999']);
        $product->forceFill(['updated_at' => now()->subDay()])->saveQuietly();
        $keeper = User::factory()->bodeguero()->create();
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

    /** @return array{0: Product, 1: ProductPresentation} */
    private function product(string $name = 'Arroz 1 lb'): array
    {
        $unit = MeasurementUnit::factory()->create(['name' => fake()->unique()->word(), 'symbol' => fake()->unique()->lexify('u??')]);
        $product = Product::factory()->for($unit, 'baseUnit')->create(['name' => $name]);

        return [$product, ProductPresentation::factory()->for($product)->base()->create(['name' => 'Unidad', 'barcode' => null])];
    }
}
