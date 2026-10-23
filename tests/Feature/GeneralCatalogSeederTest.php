<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use Database\Seeders\GeneralCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GeneralCatalogSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_adds_the_general_catalog_once_and_leaves_what_the_client_changed(): void
    {
        $edited = Category::query()->create(['name' => 'Galletas y wafers', 'slug' => 'galletas', 'is_active' => false]);
        Brand::query()->create(['name' => 'Coca Cola', 'slug' => 'coca-cola'])->delete();

        $this->seed(GeneralCatalogSeeder::class);
        $this->seed(GeneralCatalogSeeder::class);

        $this->assertSame(count(GeneralCatalogSeeder::CATEGORIES), Category::withTrashed()->count());
        $this->assertSame(count(GeneralCatalogSeeder::BRANDS), Brand::withTrashed()->count());
        $this->assertSame(count(GeneralCatalogSeeder::BRANDS), collect(GeneralCatalogSeeder::BRANDS)->map(fn (string $name) => Str::slug($name))->unique()->count(), 'no two brands share a slug');

        $this->assertSame('Galletas y wafers', $edited->fresh()->name);
        $this->assertFalse($edited->fresh()->is_active);
        $this->assertTrue(Brand::withTrashed()->where('slug', 'coca-cola')->sole()->trashed(), 'a brand the client deleted stays deleted');
        $this->assertSame('Arroz, frijoles, maíz, lentejas, semillas', Category::query()->where('name', 'Granos básicos')->value('description'));
        $this->assertTrue(Brand::query()->where('name', "Lay's")->exists());
    }
}
