<?php

namespace Tests\Feature;

use App\DeliveryRunStatus;
use App\Models\Customer;
use App\Models\CustomerReceivable;
use App\Models\DeliveryRun;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\RouteStop;
use App\Models\SalesRoute;
use App\Models\User;
use App\OrderStatus;
use App\UserRole;
use Database\Seeders\FieldTestDeliverySeeder;
use Database\Seeders\FieldTestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FieldTestSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_loads_a_full_route_for_the_preventista_and_does_not_duplicate_when_run_again(): void
    {
        User::factory()->create(['role' => UserRole::Admin]);
        $preventista = User::factory()->create(['role' => UserRole::Preventista, 'email' => 'preventista@gmail.com']);

        $this->seed(FieldTestSeeder::class);
        $this->seed(FieldTestSeeder::class);

        $route = SalesRoute::query()->where('code', FieldTestSeeder::ROUTE_CODE)->sole();
        $this->assertSame($preventista->id, $route->salesperson_id);
        $this->assertSame(200, Product::query()->where('sku', 'like', 'CAMPO-%')->count());
        $this->assertSame(40, Customer::query()->where('code', 'like', 'CAMPO-%')->count());
        $this->assertSame(240, RouteStop::query()->where('sales_route_id', $route->id)->count());

        $stock = fn (string $sku): float => (float) InventoryStock::query()->whereRelation('product', 'sku', $sku)->max('quantity_on_hand');
        $this->assertSame(0.0, $stock('CAMPO-020'));
        $this->assertSame(3.0, $stock('CAMPO-010'));
        $this->assertGreaterThan(0, $stock('CAMPO-001'));
        $this->assertSame(27, CustomerReceivable::query()->whereRelation('customer', 'code', 'like', 'CAMPO-%')->count(), '12 customers with one balance, 5 with three');

        Sanctum::actingAs($preventista, $preventista->apiAbilities());
        $this->getJson('/api/v1/sync/pull/customers')->assertOk()->assertJsonCount(40, 'records');
        $credit = collect($this->getJson('/api/v1/sync/pull/customer_credit')->json('records'))->keyBy('customer_id');
        $customer = fn (int $n): string => Customer::query()->where('code', sprintf('CAMPO-%02d', $n))->value('id');
        $this->assertFalse($credit[$customer(1)]['allows_credit'] ?? false, 'cash customer');
        $this->assertTrue($credit[$customer(22)]['allows_credit']);
        $this->assertSame('500.0000', $credit[$customer(23)]['credit_limit']);
        $this->assertNotSame('0.0000', $credit[$customer(38)]['overdue']);
    }

    public function test_it_loads_a_run_for_the_driver_through_the_warehouse_steps_and_waits_until_it_leaves(): void
    {
        User::factory()->create(['role' => UserRole::Admin]);
        User::factory()->create(['role' => UserRole::Preventista, 'email' => 'preventista@gmail.com']);
        $driver = User::factory()->repartidor()->create(['email' => 'repartidor@gmail.com']);
        $this->seed(FieldTestSeeder::class);

        $this->seed(FieldTestDeliverySeeder::class);
        $this->seed(FieldTestDeliverySeeder::class);

        $run = DeliveryRun::query()->sole();
        $this->assertSame(DeliveryRunStatus::Loaded, $run->status);
        $this->assertSame($driver->id, $run->driver_id);
        $this->assertSame(FieldTestDeliverySeeder::STOPS, $run->runOrders()->count());
        $this->assertSame(OrderStatus::Loaded, $run->runOrders()->first()->order->status);

        Sanctum::actingAs($driver, $driver->apiAbilities());
        $this->getJson('/api/v1/sync/pull/delivery_runs')->assertJsonCount(1, 'records')->assertJsonCount(FieldTestDeliverySeeder::STOPS, 'records.0.orders');
        $this->getJson('/api/v1/sync/pull/customers')->assertJsonCount(FieldTestDeliverySeeder::STOPS, 'records');
    }
}
