<?php

namespace Tests\Unit\Services;

use App\Models\PriceTier;
use App\Models\ProductPresentation;
use App\Services\PresentationPriceResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PresentationPriceResolverTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_uses_normal_price_below_first_tier(): void
    {
        $presentation = ProductPresentation::factory()->create(['sale_price' => '12']);
        PriceTier::factory()->for($presentation, 'presentation')->create([
            'min_quantity' => '12',
            'unit_price' => '10.50',
        ]);

        $result = app(PresentationPriceResolver::class)->resolve($presentation, '11');

        $this->assertSame('12.0000', $result['unit_price']);
        $this->assertSame('presentation', $result['source']);
        $this->assertNull($result['price_tier_id']);
    }

    public function test_uses_matching_active_tier_during_its_validity(): void
    {
        $presentation = ProductPresentation::factory()->create(['sale_price' => '12']);
        $tier = PriceTier::factory()->for($presentation, 'presentation')->create([
            'min_quantity' => '12',
            'max_quantity' => '23',
            'unit_price' => '10.50',
            'starts_at' => '2026-01-01',
            'ends_at' => '2026-12-31',
        ]);

        $result = app(PresentationPriceResolver::class)->resolve(
            $presentation,
            '18',
            CarbonImmutable::parse('2026-06-15'),
        );

        $this->assertSame('10.5000', $result['unit_price']);
        $this->assertSame('price_tier', $result['source']);
        $this->assertSame($tier->id, $result['price_tier_id']);
    }

    public function test_ignores_expired_tier(): void
    {
        $presentation = ProductPresentation::factory()->create(['sale_price' => '12']);
        PriceTier::factory()->for($presentation, 'presentation')->create([
            'min_quantity' => '12',
            'unit_price' => '10.50',
            'ends_at' => '2025-12-31',
        ]);

        $result = app(PresentationPriceResolver::class)->resolve(
            $presentation,
            '18',
            CarbonImmutable::parse('2026-06-15'),
        );

        $this->assertSame('12.0000', $result['unit_price']);
        $this->assertSame('presentation', $result['source']);
    }
}
