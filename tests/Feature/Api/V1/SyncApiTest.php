<?php

namespace Tests\Feature\Api\V1;

use App\CustomerVisitType;
use App\DeliveryOrderStatus;
use App\DeliveryRunStatus;
use App\Models\Customer;
use App\Models\CustomerCreditAuthorization;
use App\Models\CustomerCreditProfile;
use App\Models\CustomerReceivable;
use App\Models\CustomerVisit;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunItem;
use App\Models\DeliveryRunOrder;
use App\Models\InventoryStock;
use App\Models\MeasurementUnit;
use App\Models\Order;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\RouteStop;
use App\Models\SalesRoute;
use App\Models\SyncOperation;
use App\Models\User;
use App\Models\Warehouse;
use App\OrderPriceSource;
use App\OrderStatus;
use App\PaymentTerm;
use App\SettlementMethod;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SyncApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_manifest_tells_each_role_what_it_can_download(): void
    {
        $preventista = User::factory()->preventista()->create();
        Sanctum::actingAs($preventista, $preventista->apiAbilities());

        $this->getJson('/api/v1/sync')
            ->assertOk()
            ->assertJsonPath('user.role', 'preventista')
            ->assertJsonPath('resources.customers.available', true)
            ->assertJsonPath('resources.customers.mode', 'full')
            ->assertJsonPath('resources.products.mode', 'delta')
            ->assertJsonPath('resources.delivery_runs.available', false)
            ->assertJsonPath('catalogs.payment_methods.1.value', 'bank_transfer')
            ->assertJsonPath('catalogs.payment_methods.1.requires_reference', false)
            ->assertJsonFragment(['operations' => ['order.create', 'visit.no_sale', 'delivery.outcome', 'delivery.payment', 'customer.payment']]);
        $this->getJson('/api/v1/sync/pull/delivery_runs')->assertForbidden();
        $this->getJson('/api/v1/sync/pull/no-existe')->assertNotFound();

        $driver = User::factory()->repartidor()->create();
        Sanctum::actingAs($driver, $driver->apiAbilities());
        $this->getJson('/api/v1/sync')
            ->assertJsonPath('resources.delivery_runs.available', true)
            ->assertJsonPath('resources.customers.available', false);
    }

    public function test_catalog_changes_are_downloaded_incrementally_including_prices_and_deletions(): void
    {
        $user = User::factory()->preventista()->create();
        Sanctum::actingAs($user, $user->apiAbilities());
        [$rice, $unit] = $this->product('Arroz', '10');
        [$beans] = $this->product('Frijol', '20');
        $this->travel(5)->seconds();

        $first = $this->getJson('/api/v1/sync/pull/products?limit=1')
            ->assertOk()
            ->assertJsonPath('mode', 'delta')
            ->assertJsonPath('has_more', true)
            ->assertJsonCount(1, 'records');
        $second = $this->getJson('/api/v1/sync/pull/products?limit=1&cursor='.$first->json('next_cursor'))
            ->assertJsonCount(1, 'records');
        $this->assertEqualsCanonicalizing([$rice->id, $beans->id], [$first->json('records.0.id'), $second->json('records.0.id')]);
        $cursor = $this->getJson('/api/v1/sync/pull/products?cursor='.$second->json('next_cursor'))
            ->assertJsonCount(0, 'records')
            ->assertJsonPath('has_more', false)
            ->json('next_cursor');

        $this->travel(1)->minutes();
        PriceTier::factory()->for($unit, 'presentation')->create(['min_quantity' => '12', 'max_quantity' => null, 'unit_price' => '9.0000']);
        $beans->delete();
        $this->travel(5)->seconds();

        $changes = $this->getJson('/api/v1/sync/pull/products?cursor='.$cursor)->assertOk();
        $records = collect($changes->json('records'))->keyBy('id');

        $this->assertCount(2, $records);
        $this->assertSame('9.0000', $records[$rice->id]['presentations'][0]['price_tiers'][0]['unit_price']);
        $this->assertTrue($records[$beans->id]['deleted']);
    }

    public function test_customers_routes_and_credit_are_limited_to_the_preventistas_routes(): void
    {
        [$preventista, $customer, $stop] = $this->routeContext();
        Customer::factory()->create(['business_name' => 'Cliente ajeno']);
        CustomerCreditProfile::factory()->create(['customer_id' => $customer->id, 'credit_limit' => '1000.0000']);
        CustomerReceivable::factory()->for($customer)->create(['outstanding_amount' => '300.0000', 'due_date' => today()->subDay()]);
        Sanctum::actingAs($preventista, $preventista->apiAbilities());

        $this->getJson('/api/v1/sync/pull/customers')
            ->assertOk()
            ->assertJsonPath('mode', 'full')
            ->assertJsonCount(1, 'records')
            ->assertJsonPath('records.0.id', $customer->id);
        $this->getJson('/api/v1/sync/pull/routes')
            ->assertJsonCount(1, 'records')
            ->assertJsonPath('records.0.stops.0.id', $stop->id);
        $this->getJson('/api/v1/sync/pull/customer_credit')
            ->assertJsonPath('records.0.allows_credit', true)
            ->assertJsonPath('records.0.outstanding', '300.0000')
            ->assertJsonPath('records.0.overdue', '300.0000')
            ->assertJsonPath('records.0.available_credit', '700.0000');
    }

    public function test_an_order_taken_offline_is_created_confirmed_and_kept_despite_price_credit_and_stock_changes(): void
    {
        [$preventista, $customer, $stop] = $this->routeContext();
        [, $unit] = $this->product('Arroz', '10', onHand: '5');
        CustomerCreditProfile::factory()->create(['customer_id' => $customer->id, 'credit_limit' => '100.0000']);
        Sanctum::actingAs($preventista, $preventista->apiAbilities());
        $takenAt = now()->subHours(5)->startOfSecond();
        $operation = [
            'client_reference' => 'tel-7:pedido-1',
            'type' => 'order.create',
            'payload' => [
                'customer_id' => $customer->id,
                'route_stop_id' => $stop->id,
                'order_date' => $takenAt->toDateString(),
                'payment_term' => PaymentTerm::Credit->value,
                'confirm' => true,
                'items' => [
                    ['product_presentation_id' => $unit->id, 'quantity' => '20', 'unit_price' => '9.5000'],
                ],
                'location' => ['latitude' => 14.07, 'longitude' => -87.19, 'captured_at' => $takenAt->toIso8601String()],
            ],
        ];

        $this->postJson('/api/v1/sync/push', ['operations' => [$operation]])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'accepted')
            ->assertJsonPath('results.0.data.status', OrderStatus::Confirmed->value);

        $order = Order::query()->with('items')->sole();
        $item = $order->items->sole();
        $this->assertSame('tel-7:pedido-1', $order->client_reference);
        $this->assertSame('190.0000', $order->total);
        $this->assertSame(OrderPriceSource::Override, $item->price_source);
        $this->assertSame('10.0000', $item->standard_unit_price);
        $this->assertSame('Precio con el que se tomó el pedido sin conexión.', $item->override_reason);
        $this->assertSame('90.0000', CustomerCreditAuthorization::query()->sole()->exceeded_by);
        $this->assertSame('20.000000', InventoryStock::query()->sole()->quantity_reserved);
        $visit = CustomerVisit::query()->sole();
        $this->assertSame(CustomerVisitType::OrderTaken, $visit->type);
        $this->assertTrue($visit->occurred_at->equalTo($takenAt));

        $this->postJson('/api/v1/sync/push', ['operations' => [$operation]])
            ->assertJsonPath('results.0.status', 'duplicate')
            ->assertJsonPath('results.0.data.id', $order->id);
        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, SyncOperation::query()->sole()->attempts);
    }

    public function test_online_orders_still_require_permission_to_change_prices(): void
    {
        [$preventista, $customer, $stop] = $this->routeContext();
        [, $unit] = $this->product('Arroz', '10');
        Sanctum::actingAs($preventista, $preventista->apiAbilities());

        $this->postJson('/api/v1/orders', [
            'customer_id' => $customer->id,
            'route_stop_id' => $stop->id,
            'order_date' => now()->toDateString(),
            'payment_term' => PaymentTerm::Cash->value,
            'items' => [['product_presentation_id' => $unit->id, 'quantity' => '2', 'unit_price' => '1']],
        ])->assertForbidden();
        $this->assertSame(0, Order::query()->count());

        $this->postJson('/api/v1/orders', [
            'customer_id' => $customer->id,
            'route_stop_id' => $stop->id,
            'order_date' => now()->toDateString(),
            'payment_term' => PaymentTerm::Cash->value,
            'confirm' => true,
            'items' => [['product_presentation_id' => $unit->id, 'quantity' => '2']],
        ])->assertCreated()
            ->assertJsonPath('data.status', OrderStatus::Confirmed->value)
            ->assertJsonPath('data.total', '20.0000');
    }

    public function test_a_driver_queue_is_applied_in_order_and_a_bad_operation_does_not_stop_the_rest(): void
    {
        $driver = User::factory()->repartidor()->create();
        $run = DeliveryRun::factory()->create(['driver_id' => $driver->id, 'status' => DeliveryRunStatus::InTransit]);
        $order = Order::factory()->create(['status' => OrderStatus::InTransit]);
        $runOrder = DeliveryRunOrder::factory()->create([
            'delivery_run_id' => $run->id,
            'order_id' => $order->id,
            'visit_order' => 1,
            'status' => DeliveryOrderStatus::Loaded,
        ]);
        $item = DeliveryRunItem::factory()->create([
            'delivery_run_order_id' => $runOrder->id,
            'requested_quantity' => '3',
            'loaded_quantity' => '3',
            'unit_price' => '10.0000',
        ]);
        Sanctum::actingAs($driver, $driver->apiAbilities());
        $ids = ['delivery_run_id' => $run->id, 'run_order_id' => $runOrder->id];

        $this->getJson('/api/v1/sync/pull/delivery_runs')
            ->assertOk()
            ->assertJsonPath('records.0.id', $run->id);

        $response = $this->postJson('/api/v1/sync/push', ['operations' => [
            ['client_reference' => 'tel-9:1', 'type' => 'delivery.outcome', 'payload' => [...$ids,
                'receiver_name' => 'Doña Marta',
                'items' => [['id' => $item->id, 'delivered_quantity' => '3', 'returned_quantity' => '0', 'damaged_quantity' => '0', 'missing_quantity' => '0']],
                'location' => ['latitude' => 14.1, 'longitude' => -87.2],
            ]],
            ['client_reference' => 'tel-9:2', 'type' => 'delivery.payment', 'payload' => ['delivery_run_id' => 'no-es-un-id']],
            ['client_reference' => 'tel-9:3', 'type' => 'delivery.payment', 'payload' => [...$ids,
                'allocations' => [['method' => SettlementMethod::BankTransfer->value, 'amount' => '30', 'reference' => null]],
            ]],
        ]])->assertOk();

        $this->assertSame(['accepted', 'rejected', 'accepted'], array_column($response->json('results'), 'status'));
        $this->assertSame(DeliveryOrderStatus::Delivered, $runOrder->refresh()->status);
        $this->assertSame('14.1000000', $runOrder->latitude);
        $this->assertSame('30.0000', $run->refresh()->transfer_total);
        $this->assertSame(2, CustomerVisit::query()->count());
        $this->assertSame(1, SyncOperation::query()->where('status', SyncOperation::REJECTED)->count());
    }

    public function test_offline_credit_delivery_to_a_customer_over_the_limit_is_kept_as_an_exception(): void
    {
        $driver = User::factory()->repartidor()->create();
        $run = DeliveryRun::factory()->create(['driver_id' => $driver->id, 'status' => DeliveryRunStatus::InTransit]);
        $order = Order::factory()->create(['status' => OrderStatus::InTransit]);
        CustomerCreditProfile::factory()->create(['customer_id' => $order->customer_id, 'credit_limit' => '10.0000']);
        $runOrder = DeliveryRunOrder::factory()->create([
            'delivery_run_id' => $run->id,
            'order_id' => $order->id,
            'visit_order' => 1,
            'status' => DeliveryOrderStatus::AwaitingPayment,
            'outcome_status' => DeliveryOrderStatus::Delivered,
            'delivered_total' => '50.0000',
            'balance_due' => '50.0000',
        ]);
        Sanctum::actingAs($driver, $driver->apiAbilities());
        $payment = ['delivery_run_id' => $run->id, 'run_order_id' => $runOrder->id, 'allocations' => [['method' => SettlementMethod::Credit->value, 'amount' => '50', 'reference' => null]]];

        $this->postJson("/api/v1/delivery-runs/{$run->id}/orders/{$runOrder->id}/payments", $payment)
            ->assertUnprocessable();
        $this->postJson('/api/v1/sync/push', ['operations' => [
            ['client_reference' => 'tel-9:credito', 'type' => 'delivery.payment', 'payload' => $payment],
        ]])->assertJsonPath('results.0.status', 'accepted');

        $this->assertSame(DeliveryOrderStatus::Delivered, $runOrder->refresh()->status);
        $this->assertSame('50.0000', $runOrder->balance_due);
        $this->assertSame('40.0000', CustomerCreditAuthorization::query()->sole()->exceeded_by);
    }

    public function test_no_sale_visits_sync_and_many_operations_are_not_rate_limited(): void
    {
        [$preventista, $customer] = $this->routeContext();
        Sanctum::actingAs($preventista, $preventista->apiAbilities());
        $operations = collect(range(1, 100))->map(fn (int $number): array => [
            'client_reference' => "tel-7:visita-{$number}",
            'type' => 'visit.no_sale',
            'payload' => ['customer_id' => $customer->id, 'reason' => 'business_closed'],
        ])->all();

        $this->postJson('/api/v1/sync/push', ['operations' => $operations])->assertOk();
        $this->postJson('/api/v1/sync/push', ['operations' => array_slice($operations, 0, 30)])->assertOk();

        $this->assertSame(100, CustomerVisit::query()->where('type', CustomerVisitType::NoSale)->count());
        $this->assertSame(100, SyncOperation::query()->where('status', SyncOperation::ACCEPTED)->count());
    }

    /** @return array{0: User, 1: Customer, 2: RouteStop} */
    private function routeContext(): array
    {
        $user = User::factory()->preventista()->create();
        $customer = Customer::factory()->create();
        $route = SalesRoute::factory()->for($user, 'salesperson')->create();
        $stop = RouteStop::factory()->for($route)->for($customer)->create();

        return [$user, $customer, $stop];
    }

    /** @return array{0: Product, 1: ProductPresentation} */
    private function product(string $name, string $price, string $onHand = '0'): array
    {
        $unit = MeasurementUnit::factory()->create(['name' => fake()->unique()->word(), 'symbol' => fake()->unique()->lexify('u??')]);
        $product = Product::factory()->for($unit, 'baseUnit')->create(['name' => $name]);
        $presentation = ProductPresentation::factory()->for($product)->base()->create(['name' => 'Unidad', 'sale_price' => $price]);
        InventoryStock::query()->create([
            'warehouse_id' => Warehouse::query()->where('is_default', true)->value('id') ?? Warehouse::factory()->default()->create()->id,
            'product_id' => $product->id,
            'quantity_on_hand' => $onHand,
            'quantity_reserved' => '0',
            'reorder_point' => '0',
        ]);

        return [$product, $presentation];
    }
}
