<?php

namespace Tests\Unit\Services;

use App\Models\MeasurementUnit;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductPresentation;
use App\Services\PresentationConversionSuggester;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PresentationConversionSuggesterTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_suggests_largest_presentations_then_base_units_and_applies_tier_price(): void
    {
        $unit = MeasurementUnit::factory()->create(['name' => 'Unidad', 'symbol' => 'u']);
        $product = Product::factory()->create(['base_unit_id' => $unit->id]);
        $base = ProductPresentation::factory()->for($product)->base()->create([
            'name' => 'Unidad',
            'sale_price' => '12',
        ]);
        PriceTier::factory()->for($base, 'presentation')->create([
            'min_quantity' => '6',
            'max_quantity' => '11',
            'unit_price' => '10',
        ]);
        ProductPresentation::factory()->for($product)->create([
            'name' => 'Paquete 12',
            'conversion_factor' => '12',
            'sale_price' => '130',
        ]);
        ProductPresentation::factory()->for($product)->create([
            'name' => 'Caja 24',
            'conversion_factor' => '24',
            'sale_price' => '240',
        ]);

        $suggestion = app(PresentationConversionSuggester::class)->suggest($product, '30');

        $this->assertSame('30', $suggestion['requested_base_quantity']);
        $this->assertSame('Caja 24', $suggestion['components'][0]['name']);
        $this->assertSame('1', $suggestion['components'][0]['count']);
        $this->assertSame('Unidad', $suggestion['components'][1]['name']);
        $this->assertSame('6', $suggestion['components'][1]['count']);
        $this->assertSame('price_tier', $suggestion['components'][1]['price_source']);
        $this->assertSame('300.0000', $suggestion['estimated_total']);
        $this->assertTrue($suggestion['is_exact']);
    }

    public function test_decimal_product_can_suggest_fraction_of_base_presentation(): void
    {
        $unit = MeasurementUnit::factory()->create([
            'name' => 'Kilogramo',
            'symbol' => 'kg',
            'decimal_places' => 3,
        ]);
        $product = Product::factory()->create([
            'base_unit_id' => $unit->id,
            'allows_decimal' => true,
        ]);
        ProductPresentation::factory()->for($product)->base()->create([
            'name' => 'Kilogramo',
            'sale_price' => '40',
        ]);

        $suggestion = app(PresentationConversionSuggester::class)->suggest($product, '0.75');

        $this->assertSame('0.75', $suggestion['components'][0]['count']);
        $this->assertSame('30.0000', $suggestion['estimated_total']);
        $this->assertSame('0', $suggestion['remaining_base_quantity']);
        $this->assertTrue($suggestion['is_exact']);
    }
}
