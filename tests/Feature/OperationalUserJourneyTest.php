<?php

namespace Tests\Feature;

use App\DeliveryOrderStatus;
use App\DeliveryRunStatus;
use App\Models\Customer;
use App\Models\DeliveryRun;
use App\Models\InventoryStock;
use App\Models\MeasurementUnit;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\RouteStop;
use App\Models\SalesRoute;
use App\Models\User;
use App\Models\Warehouse;
use App\OrderStatus;
use App\PaymentTerm;
use App\SettlementMethod;
use App\Weekday;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OperationalUserJourneyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_users_complete_the_daily_flow_from_customer_creation_to_delivery_settlement(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $salesperson = User::factory()->preventista()->create();
        $warehouseUser = User::factory()->create();
        $driver = User::factory()->repartidor()->create();
        $warehouse = Warehouse::query()->where('is_default', true)->firstOrFail();
        $unit = MeasurementUnit::factory()->create(['name' => 'Unidad', 'symbol' => 'ud']);
        $product = Product::factory()->for($unit, 'baseUnit')->create([
            'sku' => 'FLUJO-001',
            'name' => 'Producto de prueba integral',
        ]);
        $presentation = ProductPresentation::factory()->for($product)->base()->create([
            'name' => 'Unidad',
            'sale_price' => '25.0000',
        ]);
        $stock = InventoryStock::factory()->for($warehouse)->for($product)->create([
            'quantity_on_hand' => '20.000000',
            'quantity_reserved' => '0.000000',
        ]);

        $this->actingAs($supervisor)->post(route('customers.store'), [
            'code' => 'CLI-FLUJO-001',
            'business_name' => 'Pulpería Flujo Completo',
            'address' => 'Barrio El Centro, local 10',
            'is_active' => '1',
        ])->assertRedirect(route('customers.index'));
        $customer = Customer::query()->where('code', 'CLI-FLUJO-001')->sole();

        $this->actingAs($supervisor)->post(route('routes.store'), [
            'code' => 'RUTA-FLUJO-01',
            'name' => 'Ruta Flujo Completo',
            'salesperson_id' => $salesperson->id,
            'driver_id' => $driver->id,
            'is_active' => '1',
        ])->assertRedirect();
        $salesRoute = SalesRoute::query()->where('code', 'RUTA-FLUJO-01')->sole();

        $this->actingAs($supervisor)->post(route('routes.stops.store', $salesRoute), [
            'customer_id' => $customer->id,
            'visit_day' => Weekday::Monday->value,
            'visit_order' => 1,
            'is_active' => '1',
        ])->assertRedirect(route('routes.show', $salesRoute));
        $routeStop = RouteStop::query()->whereBelongsTo($salesRoute)->sole();

        $this->actingAs($salesperson)->post(route('orders.store'), [
            'client_reference' => 'FULL-WEB-FLOW-ORDER-001',
            'customer_id' => $customer->id,
            'route_stop_id' => $routeStop->id,
            'order_date' => today()->toDateString(),
            'requested_delivery_date' => today()->addDay()->toDateString(),
            'payment_term' => PaymentTerm::Cash->value,
            'notes' => 'Pedido creado durante la prueba integral.',
        ])->assertRedirect();
        $order = Order::query()->where('client_reference', 'FULL-WEB-FLOW-ORDER-001')->sole();

        $this->actingAs($salesperson)->post(route('orders.items.store', $order), [
            'product_presentation_id' => $presentation->id,
            'quantity' => '5',
            'notes' => null,
        ])->assertRedirect(route('orders.show', $order));
        $this->actingAs($salesperson)->post(route('orders.confirm', $order))
            ->assertRedirect(route('orders.index'));

        $this->assertSame(OrderStatus::Confirmed, $order->refresh()->status);
        $this->assertSame('5.000000', $stock->refresh()->quantity_reserved);
        $this->assertSame(1, $warehouseUser->unreadNotifications()->count());
        $this->actingAs($warehouseUser)->get(route('dashboard'))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Pedidos confirmados sin asignar');

        $this->actingAs($warehouseUser)->post(route('delivery-runs.store'), [
            'warehouse_id' => $warehouse->id,
            'driver_id' => $driver->id,
            'scheduled_date' => today()->addDay()->toDateString(),
            'client_reference' => 'FULL-WEB-FLOW-RUN-001',
        ])->assertRedirect();

        $deliveryRun = DeliveryRun::query()
            ->where('client_reference', 'FULL-WEB-FLOW-RUN-001')
            ->sole();

        $this->actingAs($warehouseUser)->post(route('delivery-runs.orders.store', $deliveryRun), [
            'order_id' => $order->id,
        ])->assertRedirect();

        $runOrder = $deliveryRun->runOrders()->sole();
        $runItem = $runOrder->items()->sole();

        $this->actingAs($warehouseUser)->post(route('delivery-runs.preparation.start', $deliveryRun))
            ->assertRedirect();

        $this->actingAs($warehouseUser)->put(route('delivery-runs.preparation.update', $deliveryRun), [
            'items' => [[
                'id' => $runItem->id,
                'prepared_quantity' => 5,
            ]],
        ])->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($warehouseUser)->post(route('delivery-runs.load', $deliveryRun))
            ->assertRedirect();

        $this->assertSame(DeliveryRunStatus::Loaded, $deliveryRun->fresh()->status);
        $this->assertSame(1, $driver->unreadNotifications()->count());

        $this->actingAs($driver)->get(route('dashboard'))
            ->assertOk()
            ->assertSee($deliveryRun->run_number)
            ->assertSee('Tu jornada está en marcha');

        $this->actingAs($driver)->post(route('delivery-runs.depart', $deliveryRun))
            ->assertRedirect();

        $this->actingAs($driver)->put(route('delivery-runs.orders.outcome', [$deliveryRun, $runOrder]), [
            'receiver_name' => 'Carlos Comprador',
            'latitude' => 14.0723,
            'longitude' => -87.1921,
            'items' => [[
                'id' => $runItem->id,
                'delivered_quantity' => 5,
                'returned_quantity' => 0,
                'damaged_quantity' => 0,
                'missing_quantity' => 0,
            ]],
        ])->assertRedirect(route('delivery-runs.show', $deliveryRun).'#stop-'.$runOrder->id);

        $this->assertSame(DeliveryOrderStatus::AwaitingPayment, $runOrder->fresh()->status);

        $this->actingAs($driver)->post(route('delivery-runs.orders.payments.store', [$deliveryRun, $runOrder]), [
            'allocations' => [
                [
                    'method' => SettlementMethod::Cash->value,
                    'amount' => 60,
                ],
                [
                    'method' => SettlementMethod::BankTransfer->value,
                    'amount' => 65,
                    'reference' => 'TRX-FLUJO-001',
                ],
            ],
        ])->assertRedirect(route('delivery-runs.show', $deliveryRun).'#delivery-progress');

        $this->assertSame(DeliveryOrderStatus::Delivered, $runOrder->fresh()->status);
        $this->assertSame(DeliveryRunStatus::AwaitingSettlement, $deliveryRun->fresh()->status);
        $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);
        $this->assertSame(1, $salesperson->unreadNotifications()->count());

        $this->actingAs($supervisor)->get(route('dashboard'))
            ->assertOk()
            ->assertSee($deliveryRun->run_number)
            ->assertSee('Jornadas por liquidar');

        $this->actingAs($supervisor)->post(route('delivery-runs.settle', $deliveryRun), [
            'cash_declared' => 60,
        ])->assertRedirect();

        $deliveryRun->refresh();
        $stock->refresh();

        $this->assertSame(DeliveryRunStatus::Settled, $deliveryRun->status);
        $this->assertSame('15.000000', $stock->quantity_on_hand);
        $this->assertSame('0.000000', $stock->quantity_reserved);
        $this->assertSame('60.0000', $deliveryRun->cash_expected);
        $this->assertSame('60.0000', $deliveryRun->cash_declared);
        $this->assertSame('0.0000', $deliveryRun->cash_difference);

        $deliverySettlement = $runOrder->settlement()->sole();

        $this->assertDatabaseHas('delivery_settlement_lines', [
            'delivery_settlement_id' => $deliverySettlement->id,
            'method' => SettlementMethod::BankTransfer->value,
            'amount' => 65,
            'reference' => 'TRX-FLUJO-001',
        ]);

        $this->actingAs($supervisor)->get(route('delivery-runs.show', $deliveryRun))
            ->assertOk()
            ->assertSee('Jornada liquidada');
    }
}
