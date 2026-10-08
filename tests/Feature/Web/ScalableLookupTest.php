<?php

namespace Tests\Feature\Web;

use App\Models\Customer;
use App\Models\InventoryDocument;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\RouteStop;
use App\Models\SalesRoute;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ScalableLookupTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_product_lookup_searches_barcode_sku_and_name_without_returning_full_catalog(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $warehouse = Warehouse::query()->where('is_default', true)->sole();
        $target = Product::factory()->create([
            'name' => 'Aceite Dorado Familiar',
            'sku' => 'ACE-DOR-001',
        ]);
        $presentation = ProductPresentation::factory()->for($target)->create([
            'name' => 'Caja 12',
            'barcode' => '7421234567890',
            'sale_price' => '680.0000',
            'conversion_factor' => '12.000000',
        ]);
        ProductPresentation::factory()->count(25)->create();

        $this->actingAs($supervisor)
            ->getJson(route('lookups.product-presentations', [
                'q' => '7421234567890',
                'mode' => 'sellable',
                'warehouse_id' => $warehouse->id,
            ]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $presentation->id)
            ->assertJsonPath('data.0.name', 'Aceite Dorado Familiar')
            ->assertJsonPath('data.0.sku', 'ACE-DOR-001')
            ->assertJsonPath('data.0.presentation', 'Caja 12')
            ->assertJsonPath('data.0.barcode', '7421234567890');

        $response = $this->actingAs($supervisor)
            ->getJson(route('lookups.product-presentations', ['mode' => 'active']))
            ->assertOk()
            ->assertJsonPath('meta.has_more', true);
        $this->assertCount(20, $response->json('data'));

        $this->actingAs($supervisor)
            ->getJson(route('lookups.product-presentations', ['q' => 'ACE-DOR-001']))
            ->assertJsonPath('data.0.id', $presentation->id);
        $this->actingAs($supervisor)
            ->getJson(route('lookups.product-presentations', ['q' => 'Aceite Dorado']))
            ->assertJsonPath('data.0.id', $presentation->id);
    }

    public function test_customer_lookup_limits_preventista_to_active_assigned_routes(): void
    {
        $preventista = User::factory()->preventista()->create();
        $assignedCustomer = Customer::factory()->create(['business_name' => 'Pulpería Asignada']);
        $outsideCustomer = Customer::factory()->create(['business_name' => 'Pulpería Externa']);
        $assignedRoute = SalesRoute::factory()->for($preventista, 'salesperson')->create(['name' => 'Ruta Centro']);
        RouteStop::factory()->for($assignedRoute)->for($assignedCustomer)->create();
        RouteStop::factory()->for(SalesRoute::factory())->for($outsideCustomer)->create();

        $this->actingAs($preventista)
            ->getJson(route('lookups.customers', ['q' => 'Pulpería']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $assignedCustomer->id)
            ->assertJsonPath('data.0.route_stops.0.route_name', 'Ruta Centro');
    }

    public function test_order_and_inventory_pages_use_remote_product_catalog_instead_of_embedding_every_product(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $warehouse = Warehouse::query()->where('is_default', true)->sole();
        $hiddenProduct = Product::factory()->create(['name' => 'Producto que no debe precargarse']);
        ProductPresentation::factory()->for($hiddenProduct)->create();
        $order = Order::factory()->create([
            'created_by' => $supervisor->id,
            'warehouse_id' => $warehouse->id,
            'warehouse_code' => $warehouse->code,
            'warehouse_name' => $warehouse->name,
        ]);
        $document = InventoryDocument::factory()->create([
            'created_by' => $supervisor->id,
            'warehouse_id' => $warehouse->id,
        ]);

        foreach ([route('orders.show', $order), route('inventory-documents.show', $document)] as $url) {
            $this->actingAs($supervisor)
                ->get($url)
                ->assertOk()
                ->assertSee(route('lookups.product-presentations'), false)
                ->assertSee('data-remote-picker-scan', false)
                ->assertDontSee($hiddenProduct->name);
        }
    }
}
