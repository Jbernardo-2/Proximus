<?php

namespace Tests\Feature\Api\V1;

use App\CustomerAccountEntryStatus;
use App\CustomerAccountEntryType;
use App\CustomerVisitType;
use App\DeliveryOrderStatus;
use App\DeliveryRunStatus;
use App\Models\Customer;
use App\Models\CustomerAccountEntry;
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
use App\Models\OrderItem;
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
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SyncApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_manifest_tells_each_role_what_it_can_download(): void
    {
        $preventista = User::factory()->preventista()->create();
        $warehouse = Warehouse::query()->where('is_default', true)->first()
            ?? Warehouse::factory()->default()->create();
        Sanctum::actingAs($preventista, $preventista->apiAbilities());

        $this->getJson('/api/v1/sync')
            ->assertOk()
            ->assertJsonPath('user.role', 'preventista')
            ->assertJsonPath('resources.customers.available', true)
            ->assertJsonPath('resources.customers.mode', 'full')
            ->assertJsonPath('resources.route_activity.available', true)
            ->assertJsonPath('resources.route_activity.mode', 'full')
            ->assertJsonPath('resources.products.mode', 'delta')
            ->assertJsonPath('resources.delivery_runs.available', false)
            ->assertJsonPath('default_warehouse.id', $warehouse->id)
            ->assertJsonPath('catalogs.payment_methods.1.value', 'bank_transfer')
            ->assertJsonPath('catalogs.payment_methods.1.requires_reference', false)
            ->assertJsonFragment(['operations' => ['customer.create', 'order.create', 'visit.no_sale', 'customer.payment']]);
        $this->getJson('/api/v1/sync/pull/delivery_runs')->assertForbidden();
        $this->getJson('/api/v1/sync/pull/no-existe')->assertNotFound();

        $driver = User::factory()->repartidor()->create();
        Sanctum::actingAs($driver, $driver->apiAbilities());
        $this->getJson('/api/v1/sync')
            ->assertJsonPath('resources.delivery_runs.available', true)
            ->assertJsonPath('resources.customers.available', true)
            ->assertJsonPath('resources.customer_credit.available', true)
            ->assertJsonPath('resources.receivables.available', true)
            ->assertJsonPath('resources.route_activity.available', false)
            ->assertJsonFragment(['operations' => ['delivery.depart', 'delivery.outcome', 'delivery.payment', 'customer.payment']]);
    }

    public function test_daily_route_activity_reports_only_todays_assigned_stops_and_latest_result(): void
    {
        [$preventista, $customer, $stop] = $this->routeContext();
        $stop->update(['visit_day' => now()->dayOfWeekIso, 'visit_order' => 2]);
        $otherRoute = SalesRoute::factory()->create();
        RouteStop::factory()->for($otherRoute)->create(['visit_day' => now()->dayOfWeekIso]);
        RouteStop::factory()->create([
            'sales_route_id' => $stop->sales_route_id,
            'visit_day' => now()->addDay()->dayOfWeekIso,
        ]);
        Sanctum::actingAs($preventista, $preventista->apiAbilities());

        $this->getJson('/api/v1/sync/pull/route_activity')
            ->assertOk()
            ->assertJsonCount(1, 'records')
            ->assertJsonPath('records.0.id', $stop->id)
            ->assertJsonPath('records.0.customer.id', $customer->id)
            ->assertJsonPath('records.0.status', 'pending');

        CustomerVisit::factory()->create([
            'customer_id' => $customer->id,
            'user_id' => $preventista->id,
            'type' => CustomerVisitType::NoSale,
            'occurred_at' => now(),
        ]);
        $this->getJson('/api/v1/sync/pull/route_activity')
            ->assertJsonPath('records.0.status', CustomerVisitType::NoSale->value)
            ->assertJsonPath('records.0.visit.type', CustomerVisitType::NoSale->value);

        $order = Order::factory()->for($customer)->create([
            'sales_route_id' => $stop->sales_route_id,
            'route_stop_id' => $stop->id,
            'salesperson_id' => $preventista->id,
            'created_by' => $preventista->id,
            'order_date' => today(),
            'total' => '125.5000',
        ]);
        $this->getJson('/api/v1/sync/pull/route_activity')
            ->assertJsonPath('records.0.status', CustomerVisitType::OrderTaken->value)
            ->assertJsonPath('records.0.order.id', $order->id)
            ->assertJsonPath('records.0.order.total', '125.5000');
    }

    public function test_a_customer_registered_offline_can_receive_an_order_in_the_same_queue(): void
    {
        [$preventista, , $stop] = $this->routeContext();
        [, $unit] = $this->product('Café', '30', onHand: '10');
        Sanctum::actingAs($preventista, $preventista->apiAbilities());
        $customerId = (string) Str::ulid();
        $stopId = (string) Str::ulid();
        $operations = [
            ['client_reference' => 'tel-7:cliente-1', 'type' => 'customer.create', 'payload' => [
                'id' => $customerId,
                'business_name' => 'Pulpería Nueva',
                'address' => 'Colonia Kennedy, bloque 3',
                'is_active' => true,
                'route_stop' => ['id' => $stopId, 'visit_day' => now()->dayOfWeekIso],
            ]],
            ['client_reference' => 'tel-7:pedido-nuevo', 'type' => 'order.create', 'payload' => [
                'customer_id' => $customerId,
                'route_stop_id' => $stopId,
                'order_date' => today()->toDateString(),
                'payment_term' => PaymentTerm::Cash->value,
                'confirm' => true,
                'items' => [['product_presentation_id' => $unit->id, 'quantity' => '2', 'unit_price' => '30']],
            ]],
        ];

        $this->postJson('/api/v1/sync/push', ['operations' => $operations])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'accepted')
            ->assertJsonPath('results.1.status', 'accepted');

        $customer = Customer::query()->findOrFail($customerId);
        $this->assertMatchesRegularExpression('/^CLI-\d+$/', $customer->code);
        $this->assertDatabaseHas('route_stops', ['id' => $stopId, 'customer_id' => $customerId, 'sales_route_id' => $stop->sales_route_id]);
        $this->assertSame('60.0000', Order::query()->where('customer_id', $customerId)->sole()->total);

        $operations[0]['client_reference'] = 'tel-7:cliente-1-reintento';
        $this->postJson('/api/v1/sync/push', ['operations' => $operations])
            ->assertJsonPath('results.0.status', 'accepted')
            ->assertJsonPath('results.0.data.id', $customerId)
            ->assertJsonPath('results.1.status', 'duplicate');
        $this->assertSame(1, Customer::query()->where('business_name', 'Pulpería Nueva')->count());
    }

    public function test_route_activity_reports_order_and_payment_of_the_same_customer(): void
    {
        [$preventista, $customer, $stop] = $this->routeContext();
        $stop->update(['visit_day' => now()->dayOfWeekIso]);
        Order::factory()->for($customer)->create([
            'route_stop_id' => $stop->id, 'salesperson_id' => $preventista->id, 'created_by' => $preventista->id,
            'order_date' => today(), 'total' => '100.0000',
        ]);
        CustomerAccountEntry::factory()->create([
            'customer_id' => $customer->id, 'type' => CustomerAccountEntryType::CustomerPayment, 'status' => CustomerAccountEntryStatus::Active,
            'credit_amount' => '40.0000', 'debit_amount' => '0.0000', 'created_by' => $preventista->id, 'occurred_at' => now(),
        ]);
        Sanctum::actingAs($preventista, $preventista->apiAbilities());

        $this->getJson('/api/v1/sync/pull/route_activity')
            ->assertJsonPath('records.0.status', 'order_taken')
            ->assertJsonPath('records.0.has_order', true)
            ->assertJsonPath('records.0.has_payment', true)
            ->assertJsonPath('records.0.has_no_sale', false)
            ->assertJsonPath('records.0.orders_total', '100.0000')
            ->assertJsonPath('records.0.payments_total', '40.0000');
    }

    public function test_recent_orders_of_the_preventistas_customers_are_kept_for_the_customer_profile(): void
    {
        [$preventista, $customer] = $this->routeContext();
        $recent = Order::factory()->for($customer)->create(['status' => OrderStatus::Confirmed, 'order_date' => today()->subDays(3), 'total' => '80.0000']);
        OrderItem::factory()->for($recent)->create(['product_name' => 'Arroz 1 lb', 'presentation_name' => 'Unidad', 'quantity' => '4.000000', 'unit_price' => '20.0000', 'line_total' => '80.0000']);
        Order::factory()->for($customer)->create(['status' => OrderStatus::Draft, 'order_date' => today()]);
        Order::factory()->for($customer)->create(['status' => OrderStatus::Delivered, 'order_date' => today()->subDays(45)]);
        Order::factory()->create(['status' => OrderStatus::Confirmed, 'order_date' => today()]);
        Sanctum::actingAs($preventista, $preventista->apiAbilities());

        $this->getJson('/api/v1/sync/pull/recent_orders')
            ->assertOk()
            ->assertJsonCount(1, 'records')
            ->assertJsonPath('records.0.id', $recent->id)
            ->assertJsonPath('records.0.customer_id', $customer->id)
            ->assertJsonPath('records.0.total', '80.0000')
            ->assertJsonPath('records.0.status', OrderStatus::Confirmed->value)
            ->assertJsonPath('records.0.items.0.product_name', 'Arroz 1 lb')
            ->assertJsonPath('records.0.items.0.quantity', '4.000000')
            ->assertJsonPath('records.0.items.0.line_total', '80.0000');
    }

    public function test_preventista_customer_access_is_limited_to_active_assigned_routes(): void
    {
        [$preventista, $assigned] = $this->routeContext();
        $unassigned = Customer::factory()->create();
        Sanctum::actingAs($preventista, $preventista->apiAbilities());

        $this->getJson('/api/v1/customers')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $assigned->id);
        $this->getJson("/api/v1/customers/{$unassigned->id}")->assertForbidden();
        $this->postJson('/api/v1/customers', [])->assertUnprocessable();
        $this->deleteJson("/api/v1/customers/{$assigned->id}")->assertForbidden();
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

    public function test_a_driver_gets_the_contact_and_credit_of_the_customers_on_the_runs_they_carry_only(): void
    {
        $driver = User::factory()->repartidor()->create();
        $onBoard = Order::factory()->create(['status' => OrderStatus::Loaded]);
        $delivered = Order::factory()->create(['status' => OrderStatus::Delivered]);
        $otherDriver = Order::factory()->create(['status' => OrderStatus::Loaded]);
        $onBoard->customer->update(['phone' => '9999-0001', 'latitude' => '14.7650000', 'longitude' => '-88.7790000']);
        CustomerCreditProfile::factory()->create(['customer_id' => $onBoard->customer_id, 'credit_limit' => '1000.0000']);
        $run = fn (Order $order, DeliveryRunStatus $status, ?User $who = null) => DeliveryRunOrder::factory()->create([
            'delivery_run_id' => DeliveryRun::factory()->create(['driver_id' => ($who ?? $driver)->id, 'status' => $status])->id,
            'order_id' => $order->id,
        ]);
        $run($onBoard, DeliveryRunStatus::Loaded);
        $run($delivered, DeliveryRunStatus::Settled);
        $run($otherDriver, DeliveryRunStatus::Loaded, User::factory()->repartidor()->create());
        Sanctum::actingAs($driver, $driver->apiAbilities());

        $this->getJson('/api/v1/sync/pull/customers')
            ->assertOk()
            ->assertJsonCount(1, 'records')
            ->assertJsonPath('records.0.id', $onBoard->customer_id)
            ->assertJsonPath('records.0.phone', '9999-0001')
            ->assertJsonPath('records.0.latitude', '14.7650000');
        $this->getJson('/api/v1/sync/pull/customer_credit')
            ->assertJsonCount(1, 'records')
            ->assertJsonPath('records.0.available_credit', '1000.0000');
    }

    public function test_a_driver_collects_old_debts_of_the_customers_they_carry_and_no_one_else(): void
    {
        $driver = User::factory()->repartidor()->create();
        $onBoard = Order::factory()->create(['status' => OrderStatus::Loaded]);
        DeliveryRunOrder::factory()->create([
            'delivery_run_id' => DeliveryRun::factory()->create(['driver_id' => $driver->id, 'status' => DeliveryRunStatus::InTransit])->id,
            'order_id' => $onBoard->id,
        ]);
        $debt = CustomerReceivable::factory()->for($onBoard->customer)->create(['outstanding_amount' => '300.0000', 'original_amount' => '300.0000']);
        $stranger = CustomerReceivable::factory()->create(['outstanding_amount' => '90.0000', 'original_amount' => '90.0000']);
        Sanctum::actingAs($driver, $driver->apiAbilities());
        $payment = fn (CustomerReceivable $receivable, string $reference): array => ['client_reference' => $reference, 'type' => 'customer.payment', 'payload' => [
            'customer_id' => $receivable->customer_id,
            'amount' => '50',
            'payment_lines' => [['method' => 'bank_transfer', 'amount' => '50', 'reference' => 'BAC 77']],
            'allocations' => [['receivable_id' => $receivable->id, 'amount' => '50']],
        ]];

        $this->getJson('/api/v1/sync/pull/receivables')
            ->assertOk()
            ->assertJsonCount(1, 'records')
            ->assertJsonPath('records.0.id', $debt->id);

        $results = $this->postJson('/api/v1/sync/push', ['operations' => [$payment($debt, 'M004:abono'), $payment($stranger, 'M004:ajeno')]])->json('results');

        $this->assertSame(['accepted', 'rejected'], array_column($results, 'status'));
        $this->assertSame('250.0000', $debt->refresh()->outstanding_amount);
        $this->assertSame('90.0000', $stranger->refresh()->outstanding_amount, 'a customer the driver does not carry');
    }

    public function test_a_departure_recorded_without_signal_keeps_the_phone_time_and_is_accepted_if_the_run_already_left(): void
    {
        $driver = User::factory()->repartidor()->create();
        $run = DeliveryRun::factory()->create(['driver_id' => $driver->id, 'status' => DeliveryRunStatus::Loaded]);
        $order = Order::factory()->create(['status' => OrderStatus::Loaded]);
        DeliveryRunOrder::factory()->create(['delivery_run_id' => $run->id, 'order_id' => $order->id, 'visit_order' => 1, 'status' => DeliveryOrderStatus::Loaded]);
        Sanctum::actingAs($driver, $driver->apiAbilities());
        $leftAt = now()->subHour()->startOfSecond();
        $depart = fn (string $reference): array => ['client_reference' => $reference, 'type' => 'delivery.depart', 'payload' => [
            'delivery_run_id' => $run->id,
            'location' => ['status' => 'unavailable', 'captured_at' => $leftAt->toIso8601String()],
        ]];

        $this->postJson('/api/v1/sync/push', ['operations' => [$depart('tel-9:salida')]])
            ->assertJsonPath('results.0.status', 'accepted');

        $run->refresh();
        $this->assertSame(DeliveryRunStatus::InTransit, $run->status);
        $this->assertTrue($run->departed_at->equalTo($leftAt), 'the run left when the phone says, not when it synced');
        $this->assertSame(OrderStatus::InTransit, $order->refresh()->status);

        $this->postJson('/api/v1/sync/push', ['operations' => [$depart('tel-9:salida-otra-vez')]])
            ->assertJsonPath('results.0.status', 'accepted');
        $this->postJson("/api/v1/delivery-runs/{$run->id}/depart")->assertForbidden();
    }

    public function test_the_phone_departs_and_records_a_stop_not_delivered_in_one_queue(): void
    {
        $driver = User::factory()->repartidor()->create();
        $run = DeliveryRun::factory()->create(['driver_id' => $driver->id, 'status' => DeliveryRunStatus::Loaded]);
        $order = Order::factory()->create(['status' => OrderStatus::Loaded]);
        $runOrder = DeliveryRunOrder::factory()->create(['delivery_run_id' => $run->id, 'order_id' => $order->id, 'visit_order' => 1, 'status' => DeliveryOrderStatus::Loaded]);
        $item = DeliveryRunItem::factory()->create(['delivery_run_order_id' => $runOrder->id, 'requested_quantity' => '3', 'loaded_quantity' => '3', 'unit_price' => '18.0000']);
        Sanctum::actingAs($driver, $driver->apiAbilities());
        $location = ['status' => 'unavailable', 'captured_at' => now()->subMinutes(5)->toIso8601String()];

        // Exactly what the app queues when the driver records "no se entregó" before tapping "Salir".
        $response = $this->postJson('/api/v1/sync/push', ['operations' => [
            ['client_reference' => 'M004:salida', 'type' => 'delivery.depart', 'payload' => ['delivery_run_id' => $run->id, 'location' => $location]],
            ['client_reference' => 'M004:entrega', 'type' => 'delivery.outcome', 'payload' => [
                'delivery_run_id' => $run->id,
                'run_order_id' => $runOrder->id,
                'receiver_name' => null,
                'outcome_reason' => 'business_closed',
                'outcome_notes' => null,
                'items' => [['id' => $item->id, 'delivered_quantity' => '0', 'returned_quantity' => '3', 'damaged_quantity' => '0', 'missing_quantity' => '0']],
                'location' => $location,
                'outcome_reason_label' => 'Negocio cerrado',
            ]],
        ]])->assertOk();

        $this->assertSame(['accepted', 'accepted'], array_column($response->json('results'), 'status'));
        $this->assertSame(DeliveryOrderStatus::NotDelivered, $runOrder->refresh()->status);
        $this->assertSame(DeliveryRunStatus::AwaitingSettlement, $run->refresh()->status, 'its only stop is finished');
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
