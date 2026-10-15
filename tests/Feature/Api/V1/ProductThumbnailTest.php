<?php

namespace Tests\Feature\Api\V1;

use App\Models\Product;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductThumbnailTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_phone_gets_a_small_jpeg_of_the_product_photo_with_a_version_that_changes_with_the_photo(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $photo = UploadedFile::fake()->image('arroz.png', 1600, 1200)->store('products', 'public');
        $product = Product::factory()->create(['image_path' => $photo]);
        $preventista = User::factory()->create(['role' => UserRole::Preventista]);
        Sanctum::actingAs($preventista, $preventista->apiAbilities());

        $this->travel(10)->seconds();
        $record = collect($this->getJson('/api/v1/sync/pull/products')->assertOk()->json('records'))->firstWhere('id', $product->id);
        $this->assertSame(route('api.v1.sync.product-images.show', $product), $record['thumbnail_url']);
        $this->assertNotNull($record['image_version']);

        $response = $this->get($record['thumbnail_url'])->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        [$width, $height] = getimagesize($response->baseResponse->getFile()->getPathname());
        $this->assertSame([256, 192], [$width, $height]);

        $product->update(['image_path' => UploadedFile::fake()->image('arroz2.jpg', 300, 300)->store('products', 'public')]);
        $this->travel(10)->seconds();
        $this->assertNotSame($record['image_version'], collect($this->getJson('/api/v1/sync/pull/products')->json('records'))->firstWhere('id', $product->id)['image_version']);
    }

    public function test_a_product_without_photo_has_no_thumbnail_and_strangers_get_nothing(): void
    {
        $product = Product::factory()->create(['image_path' => null]);

        $this->getJson(route('api.v1.sync.product-images.show', $product))->assertUnauthorized();

        $user = User::factory()->create(['role' => UserRole::Preventista]);
        Sanctum::actingAs($user, $user->apiAbilities());
        $this->getJson(route('api.v1.sync.product-images.show', $product))->assertNotFound();
    }
}
