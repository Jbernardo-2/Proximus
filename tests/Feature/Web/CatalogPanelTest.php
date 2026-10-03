<?php

namespace Tests\Feature\Web;

use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CatalogPanelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_can_render_dashboard_and_all_module_navigation(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Resumen de la operación')
            ->assertSee(route('customers.index'), false)
            ->assertSee(route('routes.index'), false)
            ->assertSee(route('products.index'), false)
            ->assertSee(route('categories.index'), false)
            ->assertSee(route('suppliers.index'), false);
    }

    public function test_preventista_dashboard_shows_operations_without_catalog_links(): void
    {
        $user = User::factory()->preventista()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee(route('customers.index'), false)
            ->assertSee(route('routes.index'), false)
            ->assertDontSee(route('products.index'), false);
    }

    public function test_bodeguero_can_access_product_panel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/products')
            ->assertOk()
            ->assertSee('Productos');
    }

    public function test_repartidor_is_forbidden_from_panel(): void
    {
        $user = User::factory()->repartidor()->create();

        $this->actingAs($user)->get('/products')->assertForbidden();
    }

    public function test_product_index_escapes_dangerous_product_name(): void
    {
        $user = User::factory()->admin()->create();
        $product = Product::factory()->create([
            'name' => "Galleta <script>alert('xss')</script>",
        ]);
        ProductPresentation::factory()->for($product)->base()->create();

        $response = $this->actingAs($user)->get('/products');

        $response
            ->assertOk()
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee("<script>alert('xss')</script>", false);
    }
}
