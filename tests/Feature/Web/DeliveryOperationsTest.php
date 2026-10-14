<?php

namespace Tests\Feature\Web;

use App\Actions\AssignOrderToDeliveryRunAction;
use App\Actions\CompleteDeliveryStopAction;
use App\Actions\ConfirmDeliveryLoadAction;
use App\Actions\ConfirmOrderAction;
use App\Actions\CreateDeliveryRunAction;
use App\Actions\DepartDeliveryRunAction;
use App\Actions\SaveDeliveryPreparationAction;
use App\Actions\SettleDeliveryRunAction;
use App\Actions\StartDeliveryPreparationAction;
use App\DeliveryOrderStatus;
use App\DeliveryOutcomeReason;
use App\DeliveryRunStatus;
use App\InventoryMovementType;
use App\InventoryReservationStatus;
use App\Models\CustomerCreditProfile;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunOrder;
use App\Models\InventoryReservation;
use App\Models\InventoryStock;
use App\Models\MeasurementUnit;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\OrderPriceSource;
use App\OrderStatus;
use App\PaymentTerm;
use App\SettlementMethod;
use App\UserRole;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DeliveryOperationsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_delivery_pages_follow_role_and_channel_boundaries(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $bodeguero = User::factory()->create();
        $preventista = User::factory()->preventista()->create();
        $repartidor = User::factory()->repartidor()->create();

        $this->actingAs($supervisor)->get(route('delivery-runs.index'))->assertOk();
        $this->actingAs($supervisor)->get(route('delivery-runs.create'))->assertOk();
        $this->actingAs($bodeguero)->get(route('delivery-runs.index'))->assertOk();
        $this->actingAs($bodeguero)->get(route('delivery-runs.create'))->assertOk();
        $this->actingAs($preventista)->get(route('delivery-runs.index'))->assertForbidden();
        $this->actingAs($repartidor)->get(route('delivery-runs.index'))->assertOk();
        $this->actingAs($bodeguero)->get(route('vehicles.index'))->assertForbidden();
        $this->actingAs($supervisor)->get(route('vehicles.index'))->assertOk();
    }

    public function test_repartidor_only_sees_assigned_runs_without_dispatch_planning(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $driver = User::factory()->repartidor()->create();
        $otherDriver = User::factory()->repartidor()->create();
        $warehouse = Warehouse::query()->where('is_default', true)->sole();
        $ownRun = app(CreateDeliveryRunAction::class)->handle([
            'warehouse_id' => $warehouse->id,
            'driver_id' => $driver->id,
            'vehicle_id' => null,
            'scheduled_date' => now()->toDateString(),
            'client_reference' => null,
            'notes' => null,
        ], $supervisor);
        $otherRun = app(CreateDeliveryRunAction::class)->handle([
            'warehouse_id' => $warehouse->id,
            'driver_id' => $otherDriver->id,
            'vehicle_id' => null,
            'scheduled_date' => now()->addDay()->toDateString(),
            'client_reference' => null,
            'notes' => null,
        ], $supervisor);

        $this->actingAs($driver)
            ->get(route('delivery-runs.index'))
            ->assertOk()
            ->assertSee($ownRun->run_number)
            ->assertDontSee($otherRun->run_number)
            ->assertDontSee('Pedidos sin asignar')
            ->assertDontSee('Plan de despacho')
            ->assertDontSee('Nueva jornada');
        $this->actingAs($driver)->get(route('delivery-runs.show', $ownRun))->assertOk();
        $this->actingAs($driver)->get(route('delivery-runs.show', $otherRun))->assertForbidden();
    }

    public function test_repartidor_dashboard_summarizes_progress_next_stop_and_achievements(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $driver = User::factory()->repartidor()->create();
        $run = DeliveryRun::factory()->create([
            'driver_id' => $driver->id,
            'driver_name' => $driver->name,
            'created_by' => $supervisor->id,
            'status' => DeliveryRunStatus::InTransit,
            'delivered_total' => '120.0000',
            'collected_total' => '80.0000',
            'credit_total' => '40.0000',
        ]);
        $completedOrder = Order::factory()->create(['customer_name' => 'Pulpería Entregada']);
        $nextOrder = Order::factory()->create([
            'customer_name' => 'Mercadito Siguiente',
            'customer_address' => 'Barrio El Centro',
        ]);
        DeliveryRunOrder::factory()->create([
            'delivery_run_id' => $run->id,
            'order_id' => $completedOrder->id,
            'visit_order' => 1,
            'status' => DeliveryOrderStatus::Delivered,
            'completed_at' => now(),
            'completed_by' => $driver->id,
        ]);
        DeliveryRunOrder::factory()->create([
            'delivery_run_id' => $run->id,
            'order_id' => $nextOrder->id,
            'visit_order' => 2,
            'status' => DeliveryOrderStatus::Loaded,
        ]);

        $this->actingAs($driver)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Panel del repartidor')
            ->assertSee('1/2')
            ->assertSee('50%')
            ->assertSee('Mercadito Siguiente')
            ->assertSee('Primera entrega')
            ->assertSee('data-run-filter="completed"', false)
            ->assertSee(route('delivery-runs.show', $run), false);
    }

    public function test_vehicle_data_is_normalized_and_an_open_run_prevents_deactivation(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $driver = User::factory()->repartidor()->create();
        $warehouse = Warehouse::query()->where('is_default', true)->sole();

        $this->actingAs($supervisor)->post(route('vehicles.store'), [
            'code' => 'camion-01',
            'license_plate' => 'hba-1234',
            'description' => 'Camión de reparto principal',
            'is_active' => true,
        ])->assertRedirect();
        $vehicle = Vehicle::query()->sole();
        $this->assertSame('CAMION-01', $vehicle->code);
        $this->assertSame('HBA-1234', $vehicle->license_plate);

        app(CreateDeliveryRunAction::class)->handle([
            'warehouse_id' => $warehouse->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'scheduled_date' => now()->toDateString(),
            'client_reference' => null,
            'notes' => null,
        ], $supervisor);

        $this->actingAs($supervisor)->put(route('vehicles.update', $vehicle), [
            'code' => $vehicle->code,
            'license_plate' => $vehicle->license_plate,
            'description' => $vehicle->description,
            'is_active' => false,
        ])->assertSessionHasErrors('is_active');
        $this->assertTrue($vehicle->refresh()->is_active);
    }

    public function test_driver_access_cannot_be_removed_while_a_delivery_run_is_open(): void
    {
        $administrator = User::factory()->admin()->create();
        $driver = User::factory()->repartidor()->create();
        $warehouse = Warehouse::query()->where('is_default', true)->sole();
        app(CreateDeliveryRunAction::class)->handle([
            'warehouse_id' => $warehouse->id,
            'driver_id' => $driver->id,
            'vehicle_id' => null,
            'scheduled_date' => now()->toDateString(),
            'client_reference' => null,
            'notes' => null,
        ], $administrator);

        $basePayload = [
            'name' => $driver->name,
            'email' => $driver->email,
            'role' => $driver->role->value,
            'is_active' => '0',
            'password' => '',
            'password_confirmation' => '',
        ];
        $this->actingAs($administrator)->put(route('users.update', $driver), $basePayload)
            ->assertSessionHasErrors('is_active');
        $this->actingAs($administrator)->put(route('users.update', $driver), [
            ...$basePayload,
            'role' => UserRole::Supervisor->value,
            'is_active' => '1',
        ])->assertSessionHasErrors('role');

        $this->assertTrue($driver->refresh()->is_active);
        $this->assertSame(UserRole::Repartidor, $driver->role);
    }

    public function test_complete_web_flow_controls_partial_load_mixed_payments_returns_and_settlement(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $bodeguero = User::factory()->create();
        $driver = User::factory()->repartidor()->create();
        $context = $this->confirmedOrder([
            ['quantity' => '10', 'unit_price' => '10', 'on_hand' => '50'],
        ]);
        $order = $context['order'];
        $stock = $context['lines'][0]['stock'];

        $this->actingAs($bodeguero)->post(route('delivery-runs.store'), [
            'warehouse_id' => $context['warehouse']->id,
            'driver_id' => $driver->id,
            'vehicle_id' => null,
            'scheduled_date' => now()->toDateString(),
            'client_reference' => 'WEB-RUN-001',
            'notes' => 'Ruta de prueba',
        ])->assertRedirect();
        $run = DeliveryRun::query()->sole();

        $this->actingAs($bodeguero)->post(route('delivery-runs.orders.store', $run), [
            'order_id' => $order->id,
            'visit_order' => 3,
        ])->assertRedirect(route('delivery-runs.show', $run));
        $runOrder = $run->runOrders()->sole();
        $runItem = $runOrder->items()->sole();
        $this->assertSame(OrderStatus::Assigned, $order->refresh()->status);

        $this->actingAs($bodeguero)
            ->post(route('delivery-runs.preparation.start', $run))
            ->assertRedirect(route('delivery-runs.show', $run));
        $this->actingAs($bodeguero)->put(route('delivery-runs.preparation.update', $run), [
            'items' => [['id' => $runItem->id, 'prepared_quantity' => '8']],
        ])->assertRedirect(route('delivery-runs.show', $run));
        $this->actingAs($bodeguero)->get(route('delivery-runs.show', $run))
            ->assertOk()
            ->assertSee('Confirmar carga');
        $this->actingAs($bodeguero)
            ->post(route('delivery-runs.load', $run))
            ->assertRedirect(route('delivery-runs.show', $run));

        $this->assertSame(DeliveryRunStatus::Loaded, $run->refresh()->status);
        $this->assertSame(OrderStatus::Loaded, $order->refresh()->status);
        $this->assertSame('42.000000', $stock->refresh()->quantity_on_hand);
        $this->assertSame('0.000000', $stock->quantity_reserved);
        $this->assertSame(InventoryReservationStatus::Fulfilled, InventoryReservation::query()->sole()->status);
        $this->assertDatabaseHas('inventory_movements', [
            'delivery_run_id' => $run->id,
            'type' => InventoryMovementType::DispatchLoad->value,
            'quantity_on_hand_delta' => -8,
            'quantity_reserved_delta' => -8,
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'delivery_run_id' => $run->id,
            'type' => InventoryMovementType::OrderReservationRelease->value,
            'quantity_reserved_delta' => -2,
        ]);

        $this->actingAs($driver)
            ->post(route('delivery-runs.depart', $run))
            ->assertRedirect(route('delivery-runs.show', $run));
        $this->assertSame(OrderStatus::InTransit, $order->refresh()->status);

        $deliveryForm = $this->actingAs($driver)->get(route('delivery-runs.show', $run));
        $deliveryForm
            ->assertOk()
            ->assertSee('data-pending-stop="'.$runOrder->id.'"', false)
            ->assertSee('value="0" data-delivery-quantity', false)
            ->assertDontSee('value="0.000000" data-delivery-quantity', false);

        $this->actingAs($driver)->put(route('delivery-runs.orders.outcome', [$run, $runOrder]), [
            'receiver_name' => 'María López',
            'outcome_reason' => DeliveryOutcomeReason::DamagedGoods->value,
            'outcome_notes' => 'Una unidad regresó y otra llegó dañada.',
            'credit_reason' => null,
            'items' => [[
                'id' => $runItem->id,
                'delivered_quantity' => '6',
                'returned_quantity' => '1',
                'damaged_quantity' => '1',
                'missing_quantity' => '0',
            ]],
        ])->assertRedirect(route('delivery-runs.show', $run).'#stop-'.$runOrder->id);

        $this->assertSame(DeliveryRunStatus::InTransit, $run->refresh()->status);
        $this->assertSame(DeliveryOrderStatus::AwaitingPayment, $runOrder->refresh()->status);
        $this->assertSame(DeliveryOrderStatus::PartiallyDelivered, $runOrder->outcome_status);
        $this->assertSame(OrderStatus::InTransit, $order->refresh()->status);
        $this->assertSame('60.0000', $runOrder->delivered_total);
        $this->actingAs($driver)->get(route('delivery-runs.show', $run))
            ->assertOk()
            ->assertSee('data-pending-stop="'.$runOrder->id.'"', false)
            ->assertDontSee('data-completed-stop="'.$runOrder->id.'"', false)
            ->assertSee('Distribución del pago')
            ->assertSee('Crédito');

        $this->actingAs($driver)->post(route('delivery-runs.orders.payments.store', [$run, $runOrder]), [
            'allocations' => [
                [
                    'method' => SettlementMethod::Cash->value,
                    'amount' => '40',
                    'reference' => null,
                ],
                [
                    'method' => SettlementMethod::BankTransfer->value,
                    'amount' => '20',
                    'reference' => 'TRX-7788',
                ],
            ],
            'credit_reason' => null,
        ])->assertRedirect(route('delivery-runs.show', $run).'#delivery-progress');

        $this->assertSame(DeliveryRunStatus::AwaitingSettlement, $run->refresh()->status);
        $this->assertSame(DeliveryOrderStatus::PartiallyDelivered, $runOrder->refresh()->status);
        $this->assertSame(OrderStatus::PartiallyDelivered, $order->refresh()->status);
        $this->assertSame('60.0000', $run->refresh()->collected_total);
        $this->assertSame('40.0000', $run->cash_expected);
        $this->assertSame('20.0000', $run->transfer_total);
        $this->assertSame('0.0000', $run->credit_total);
        $this->assertDatabaseCount('delivery_settlements', 1);
        $this->assertDatabaseCount('delivery_settlement_lines', 2);
        $this->assertDatabaseCount('customer_account_entries', 3);
        $this->assertDatabaseCount('customer_receivables', 0);
        $this->actingAs($supervisor)->get(route('delivery-runs.show', $run))
            ->assertOk()
            ->assertSee('Transferencia bancaria')
            ->assertSee('Liquidar jornada');

        $this->actingAs($supervisor)->post(route('delivery-runs.settle', $run), [
            'cash_declared' => '40',
            'settlement_notes' => null,
        ])->assertRedirect(route('delivery-runs.show', $run));

        $this->assertSame(DeliveryRunStatus::Settled, $run->refresh()->status);
        $this->assertSame('0.0000', $run->cash_difference);
        $this->assertSame('43.000000', $stock->refresh()->quantity_on_hand);
        $this->assertDatabaseHas('inventory_movements', [
            'delivery_run_id' => $run->id,
            'type' => InventoryMovementType::DispatchReturn->value,
            'quantity_on_hand_delta' => 1,
        ]);
        $this->assertDatabaseCount('delivery_payments', 2);
        $this->actingAs($supervisor)->get(route('delivery-runs.show', $run))
            ->assertOk()
            ->assertSee('Jornada liquidada');
        $this->actingAs($bodeguero)->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee($run->run_number);
    }

    public function test_loading_is_atomic_when_any_product_has_insufficient_physical_stock(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $bodeguero = User::factory()->create();
        $driver = User::factory()->repartidor()->create();
        $context = $this->confirmedOrder([
            ['quantity' => '2', 'unit_price' => '10', 'on_hand' => '10'],
            ['quantity' => '3', 'unit_price' => '20', 'on_hand' => '1'],
        ]);
        [$run] = $this->assignedRun($context['order'], $supervisor, $driver);
        app(StartDeliveryPreparationAction::class)->handle($run, $bodeguero);
        $items = $run->runOrders()->sole()->items()->orderBy('id')->get();
        app(SaveDeliveryPreparationAction::class)->handle($run, $items->map(fn ($item): array => [
            'id' => $item->id,
            'prepared_quantity' => $item->requested_quantity,
        ])->all());

        $this->actingAs($bodeguero)->post(route('delivery-runs.load', $run))
            ->assertSessionHasErrors('quantity');

        $this->assertSame(DeliveryRunStatus::Preparing, $run->refresh()->status);
        $this->assertSame(OrderStatus::Assigned, $context['order']->refresh()->status);
        $this->assertSame('10.000000', $context['lines'][0]['stock']->refresh()->quantity_on_hand);
        $this->assertSame('2.000000', $context['lines'][0]['stock']->quantity_reserved);
        $this->assertSame('1.000000', $context['lines'][1]['stock']->refresh()->quantity_on_hand);
        $this->assertSame('3.000000', $context['lines'][1]['stock']->quantity_reserved);
        $this->assertDatabaseMissing('inventory_movements', [
            'delivery_run_id' => $run->id,
            'type' => InventoryMovementType::DispatchLoad->value,
        ]);
        $this->assertSame(2, InventoryReservation::query()
            ->where('status', InventoryReservationStatus::Active->value)
            ->count());
    }

    public function test_product_in_transit_cannot_be_archived_even_when_warehouse_balance_is_zero(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $driver = User::factory()->repartidor()->create();
        $context = $this->confirmedOrder([
            ['quantity' => '2', 'unit_price' => '10', 'on_hand' => '2'],
        ]);
        $this->loadedRun($context['order'], $supervisor, $driver);
        $product = $context['lines'][0]['product'];

        $this->assertSame('0.000000', $context['lines'][0]['stock']->refresh()->quantity_on_hand);
        $this->assertSame('0.000000', $context['lines'][0]['stock']->quantity_reserved);
        $this->actingAs($supervisor)->delete(route('products.destroy', $product))
            ->assertSessionHasErrors('product');

        $this->assertNull($product->refresh()->deleted_at);
    }

    public function test_delivery_result_must_account_for_every_loaded_unit(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $driver = User::factory()->repartidor()->create();
        $context = $this->confirmedOrder([
            ['quantity' => '5', 'unit_price' => '10', 'on_hand' => '20'],
        ]);
        [$run, $runOrder] = $this->loadedRun($context['order'], $supervisor, $driver);
        app(DepartDeliveryRunAction::class)->handle($run, $supervisor);
        $runItem = $runOrder->items()->sole();

        $this->actingAs($supervisor)->put(route('delivery-runs.orders.outcome', [$run, $runOrder]), [
            'receiver_name' => 'Cliente',
            'outcome_reason' => DeliveryOutcomeReason::StockShortage->value,
            'items' => [[
                'id' => $runItem->id,
                'delivered_quantity' => '4',
                'returned_quantity' => '0',
                'damaged_quantity' => '0',
                'missing_quantity' => '0',
            ]],
        ])->assertSessionHasErrors('items');

        $this->assertSame(DeliveryRunStatus::InTransit, $run->refresh()->status);
        $this->assertSame(DeliveryOrderStatus::Loaded, $runOrder->refresh()->status);
        $this->assertSame('0.000000', $runItem->refresh()->delivered_quantity);
    }

    public function test_whole_unit_delivery_rejects_decimal_quantities(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $driver = User::factory()->repartidor()->create();
        $context = $this->confirmedOrder([
            ['quantity' => '5', 'unit_price' => '10', 'on_hand' => '20'],
        ]);
        [$run, $runOrder] = $this->loadedRun($context['order'], $supervisor, $driver);
        app(DepartDeliveryRunAction::class)->handle($run, $driver);
        $runItem = $runOrder->items()->sole();

        $this->actingAs($driver)->put(route('delivery-runs.orders.outcome', [$run, $runOrder]), [
            'receiver_name' => 'Cliente',
            'outcome_reason' => DeliveryOutcomeReason::CustomerRejected->value,
            'items' => [[
                'id' => $runItem->id,
                'delivered_quantity' => '4.5',
                'returned_quantity' => '0.5',
                'damaged_quantity' => '0',
                'missing_quantity' => '0',
            ]],
        ])->assertSessionHasErrors('items');

        $this->assertSame(DeliveryOrderStatus::Loaded, $runOrder->refresh()->status);
        $this->assertSame('0.000000', $runItem->refresh()->delivered_quantity);
    }

    public function test_combined_payment_is_atomic_and_can_leave_an_explained_credit_balance(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $driver = User::factory()->repartidor()->create();
        $context = $this->confirmedOrder([
            ['quantity' => '5', 'unit_price' => '10', 'on_hand' => '20'],
        ]);
        [$run, $runOrder] = $this->loadedRun($context['order'], $supervisor, $driver);
        app(DepartDeliveryRunAction::class)->handle($run, $driver);
        $runItem = $runOrder->items()->sole();
        app(CompleteDeliveryStopAction::class)->handle($run, $runOrder, [
            'receiver_name' => 'Cliente',
        ], [[
            'id' => $runItem->id,
            'delivered_quantity' => '5',
            'returned_quantity' => '0',
            'damaged_quantity' => '0',
            'missing_quantity' => '0',
        ]], $driver);

        $this->actingAs($driver)->post(route('delivery-runs.orders.payments.store', [$run, $runOrder]), [
            'allocations' => [
                ['method' => SettlementMethod::Cash->value, 'amount' => '10', 'reference' => null],
                ['method' => SettlementMethod::Check->value, 'amount' => '10', 'reference' => null],
                ['method' => SettlementMethod::Credit->value, 'amount' => '30', 'reference' => null],
            ],
            'credit_reason' => 'Saldo autorizado.',
        ])->assertSessionHasErrors('allocations.1.reference');
        $this->assertDatabaseCount('delivery_payments', 0);
        $this->assertDatabaseCount('delivery_settlements', 0);

        $this->actingAs($driver)->post(route('delivery-runs.orders.payments.store', [$run, $runOrder]), [
            'allocations' => [
                ['method' => SettlementMethod::Cash->value, 'amount' => '30', 'reference' => null],
                ['method' => SettlementMethod::BankTransfer->value, 'amount' => '30', 'reference' => 'TRX-INVALIDA'],
            ],
            'credit_reason' => null,
        ])->assertSessionHasErrors('allocations');
        $this->assertDatabaseCount('delivery_payments', 0);
        $this->assertDatabaseCount('delivery_settlements', 0);

        $this->actingAs($driver)->post(route('delivery-runs.orders.payments.store', [$run, $runOrder]), [
            'allocations' => [
                ['method' => SettlementMethod::Cash->value, 'amount' => '10', 'reference' => null],
                ['method' => SettlementMethod::BankTransfer->value, 'amount' => '15', 'reference' => 'TRX-2026-001'],
                ['method' => SettlementMethod::Credit->value, 'amount' => '25', 'reference' => null],
            ],
            'credit_reason' => 'Cliente pagará el saldo en la próxima visita.',
        ])->assertRedirect(route('delivery-runs.show', $run).'#delivery-progress');

        $this->assertDatabaseCount('delivery_payments', 2);
        $this->assertDatabaseCount('delivery_settlement_lines', 3);
        $this->assertDatabaseCount('customer_receivables', 1);
        $this->assertDatabaseCount('customer_account_entries', 3);
        $this->assertSame(DeliveryOrderStatus::Delivered, $runOrder->refresh()->status);
        $this->assertSame('25.0000', $runOrder->refresh()->collected_total);
        $this->assertSame('25.0000', $runOrder->balance_due);
        $this->assertSame('Cliente pagará el saldo en la próxima visita.', $runOrder->credit_reason);
        $this->assertSame('10.0000', $run->refresh()->cash_expected);
        $this->assertSame('15.0000', $run->transfer_total);
        $this->assertSame('25.0000', $run->credit_total);
    }

    public function test_confirming_payment_clears_the_stop_and_suggests_the_next_delivery(): void
    {
        $driver = User::factory()->repartidor()->create();
        $supervisor = User::factory()->supervisor()->create();
        $run = DeliveryRun::factory()->create([
            'driver_id' => $driver->id,
            'driver_name' => $driver->name,
            'created_by' => $supervisor->id,
            'status' => DeliveryRunStatus::InTransit,
        ]);
        $completedOrder = Order::factory()->create(['status' => OrderStatus::InTransit]);
        $nextOrder = Order::factory()->create(['status' => OrderStatus::InTransit]);
        $currentStop = DeliveryRunOrder::factory()->create([
            'delivery_run_id' => $run->id,
            'order_id' => $completedOrder->id,
            'visit_order' => 1,
            'status' => DeliveryOrderStatus::AwaitingPayment,
            'outcome_status' => DeliveryOrderStatus::Delivered,
            'delivered_total' => '50.0000',
            'balance_due' => '50.0000',
            'outcome_recorded_at' => now(),
            'outcome_recorded_by' => $driver->id,
        ]);
        $nextStop = DeliveryRunOrder::factory()->create([
            'delivery_run_id' => $run->id,
            'order_id' => $nextOrder->id,
            'visit_order' => 2,
            'status' => DeliveryOrderStatus::Loaded,
        ]);

        $response = $this->actingAs($driver)->post(
            route('delivery-runs.orders.payments.store', [$run, $currentStop]),
            [
                'allocations' => [[
                    'method' => SettlementMethod::Cash->value,
                    'amount' => '50',
                    'reference' => null,
                ]],
                'credit_reason' => null,
            ],
        );

        $response
            ->assertRedirect(route('delivery-runs.show', $run).'#stop-'.$nextStop->id)
            ->assertSessionHas('success', 'Cobro registrado. Siguiente entrega: parada #2.');
        $this->assertSame(DeliveryOrderStatus::Delivered, $currentStop->refresh()->status);
        $this->assertSame(DeliveryRunStatus::InTransit, $run->refresh()->status);
    }

    public function test_credit_agreement_is_optional_and_cash_difference_requires_an_explanation(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $driver = User::factory()->repartidor()->create();
        $context = $this->confirmedOrder([
            ['quantity' => '5', 'unit_price' => '10', 'on_hand' => '20'],
        ]);
        [$run, $runOrder] = $this->loadedRun($context['order'], $supervisor, $driver);
        app(DepartDeliveryRunAction::class)->handle($run, $supervisor);
        $runItem = $runOrder->items()->sole();
        app(CompleteDeliveryStopAction::class)->handle($run, $runOrder, [
            'receiver_name' => 'Cliente',
        ], [[
            'id' => $runItem->id,
            'delivered_quantity' => '5',
            'returned_quantity' => '0',
            'damaged_quantity' => '0',
            'missing_quantity' => '0',
        ]], $supervisor);

        $this->actingAs($supervisor)->post(route('delivery-runs.orders.payments.store', [$run, $runOrder]), [
            'allocations' => [[
                'method' => SettlementMethod::Credit->value,
                'amount' => '50',
                'reference' => null,
            ]],
            'credit_reason' => null,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(DeliveryOrderStatus::Delivered, $runOrder->refresh()->status);
        $this->assertNull($runOrder->credit_reason);
        app(SettleDeliveryRunAction::class)->handle($run, [
            'cash_declared' => '0',
            'settlement_notes' => null,
        ], $supervisor);

        $this->assertSame(DeliveryRunStatus::Settled, $run->refresh()->status);
        $this->assertSame('50.0000', $run->credit_total);

        $second = $this->confirmedOrder([
            ['quantity' => '2', 'unit_price' => '10', 'on_hand' => '10'],
        ]);
        [$cashRun, $cashRunOrder] = $this->loadedRun($second['order'], $supervisor, $driver);
        app(DepartDeliveryRunAction::class)->handle($cashRun, $supervisor);
        $cashItem = $cashRunOrder->items()->sole();
        app(CompleteDeliveryStopAction::class)->handle($cashRun, $cashRunOrder, [
            'receiver_name' => 'Cliente',
        ], [[
            'id' => $cashItem->id,
            'delivered_quantity' => '2',
            'returned_quantity' => '0',
            'damaged_quantity' => '0',
            'missing_quantity' => '0',
        ]], $supervisor);
        $this->actingAs($supervisor)->post(route('delivery-runs.orders.payments.store', [$cashRun, $cashRunOrder]), [
            'allocations' => [[
                'method' => SettlementMethod::Cash->value,
                'amount' => '20',
                'reference' => null,
            ]],
            'credit_reason' => null,
        ])->assertRedirect();

        $this->actingAs($supervisor)->post(route('delivery-runs.settle', $cashRun), [
            'cash_declared' => '19',
            'settlement_notes' => null,
        ])->assertSessionHasErrors('settlement_notes');
        $this->actingAs($supervisor)->post(route('delivery-runs.settle', $cashRun), [
            'cash_declared' => '19',
            'settlement_notes' => 'Falta L 1 reportado por el repartidor.',
        ])->assertRedirect();

        $this->assertSame(DeliveryRunStatus::Settled, $cashRun->refresh()->status);
        $this->assertSame('-1.0000', $cashRun->cash_difference);
    }

    public function test_final_settlement_saves_an_optional_agreement_for_an_unexplained_cash_balance(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $driver = User::factory()->repartidor()->create();
        $context = $this->confirmedOrder([
            ['quantity' => '5', 'unit_price' => '10', 'on_hand' => '20'],
        ]);
        [$run, $runOrder] = $this->loadedRun($context['order'], $supervisor, $driver);
        app(DepartDeliveryRunAction::class)->handle($run, $driver);
        $runItem = $runOrder->items()->sole();
        app(CompleteDeliveryStopAction::class)->handle($run, $runOrder, [
            'receiver_name' => 'Cliente',
        ], [[
            'id' => $runItem->id,
            'delivered_quantity' => '5',
            'returned_quantity' => '0',
            'damaged_quantity' => '0',
            'missing_quantity' => '0',
        ]], $driver);

        $this->actingAs($supervisor)->post(route('delivery-runs.orders.payments.store', [$run, $runOrder]), [
            'allocations' => [
                ['method' => SettlementMethod::Cash->value, 'amount' => '20', 'reference' => null],
                ['method' => SettlementMethod::Credit->value, 'amount' => '30', 'reference' => null],
            ],
            'credit_reason' => 'Saldo acordado para la próxima visita.',
        ])->assertRedirect();

        $runOrder->refresh()->forceFill(['credit_reason' => null])->save();

        $this->actingAs($supervisor)->get(route('delivery-runs.show', $run))
            ->assertOk()
            ->assertSee('Saldos pendientes sin acuerdo registrado')
            ->assertSee('name="credit_reasons['.$runOrder->id.']"', false)
            ->assertSee('data-app-dialog', false);

        $this->actingAs($supervisor)->post(route('delivery-runs.settle', $run), [
            'cash_declared' => '20',
            'settlement_notes' => null,
            'credit_reasons' => [
                $runOrder->id => 'El cliente completará el saldo en su próxima visita.',
            ],
        ])->assertRedirect(route('delivery-runs.show', $run));

        $this->assertSame(DeliveryRunStatus::Settled, $run->refresh()->status);
        $this->assertSame(
            'El cliente completará el saldo en su próxima visita.',
            $runOrder->refresh()->credit_reason,
        );
    }

    public function test_cancelling_before_load_restores_orders_without_releasing_reservations(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $driver = User::factory()->repartidor()->create();
        $context = $this->confirmedOrder([
            ['quantity' => '4', 'unit_price' => '10', 'on_hand' => '12'],
        ]);
        [$run, $runOrder] = $this->assignedRun($context['order'], $supervisor, $driver);

        $this->actingAs($supervisor)->post(route('delivery-runs.cancel', $run), [
            'reason' => 'El vehículo no estará disponible.',
        ])->assertRedirect(route('delivery-runs.show', $run));

        $this->assertSame(DeliveryRunStatus::Cancelled, $run->refresh()->status);
        $this->assertSame(DeliveryOrderStatus::Cancelled, $runOrder->refresh()->status);
        $this->assertSame(OrderStatus::Confirmed, $context['order']->refresh()->status);
        $this->assertSame('4.000000', $context['lines'][0]['stock']->refresh()->quantity_reserved);
        $this->assertSame(InventoryReservationStatus::Active, InventoryReservation::query()->sole()->status);
    }

    public function test_no_delivery_can_be_requeued_only_after_settlement_and_reserves_stock_again(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $driver = User::factory()->repartidor()->create();
        $context = $this->confirmedOrder([
            ['quantity' => '5', 'unit_price' => '10', 'on_hand' => '20'],
        ]);
        [$run, $runOrder] = $this->loadedRun($context['order'], $supervisor, $driver);
        app(DepartDeliveryRunAction::class)->handle($run, $supervisor);
        $runItem = $runOrder->items()->sole();
        app(CompleteDeliveryStopAction::class)->handle($run, $runOrder, [
            'outcome_reason' => DeliveryOutcomeReason::CustomerAbsent->value,
            'outcome_notes' => 'Local cerrado.',
        ], [[
            'id' => $runItem->id,
            'delivered_quantity' => '0',
            'returned_quantity' => '5',
            'damaged_quantity' => '0',
            'missing_quantity' => '0',
        ]], $supervisor);

        $this->actingAs($supervisor)->post(route('delivery-runs.orders.requeue', [$run, $runOrder]), [
            'reason' => 'Intentar mañana.',
        ])->assertSessionHasErrors('delivery_run_order');

        app(SettleDeliveryRunAction::class)->handle($run, [
            'cash_declared' => '0',
            'settlement_notes' => null,
        ], $supervisor);
        $this->assertSame('20.000000', $context['lines'][0]['stock']->refresh()->quantity_on_hand);
        $this->assertSame('0.000000', $context['lines'][0]['stock']->quantity_reserved);

        $this->actingAs($supervisor)->post(route('delivery-runs.orders.requeue', [$run, $runOrder]), [
            'reason' => 'Cliente confirmó que abrirá mañana.',
        ])->assertRedirect(route('orders.show', $context['order']));

        $this->assertSame(OrderStatus::Confirmed, $context['order']->refresh()->status);
        $this->assertSame('5.000000', $context['lines'][0]['stock']->refresh()->quantity_reserved);
        $this->assertSame(InventoryReservationStatus::Active, InventoryReservation::query()->sole()->status);
        $this->assertDatabaseCount('inventory_reservations', 1);
    }

    public function test_nested_delivery_resources_cannot_be_mixed_between_runs(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $driver = User::factory()->repartidor()->create();
        $first = $this->confirmedOrder([['quantity' => '1', 'unit_price' => '10', 'on_hand' => '5']]);
        $second = $this->confirmedOrder([['quantity' => '1', 'unit_price' => '10', 'on_hand' => '5']]);
        [$firstRun] = $this->assignedRun($first['order'], $supervisor, $driver);
        [, $secondRunOrder] = $this->assignedRun($second['order'], $supervisor, $driver);

        $this->actingAs($supervisor)
            ->delete(route('delivery-runs.orders.destroy', [$firstRun, $secondRunOrder]))
            ->assertNotFound();

        $this->assertSame(OrderStatus::Assigned, $first['order']->refresh()->status);
        $this->assertSame(OrderStatus::Assigned, $second['order']->refresh()->status);
        $this->assertDatabaseCount('delivery_run_orders', 2);
    }

    public function test_warehouse_prepares_each_order_separately_before_atomic_load(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $bodeguero = User::factory()->create();
        $driver = User::factory()->repartidor()->create();
        $first = $this->confirmedOrder([['quantity' => '15', 'unit_price' => '10', 'on_hand' => '30']]);
        $second = $this->confirmedOrder([['quantity' => '7', 'unit_price' => '12', 'on_hand' => '20']]);
        $run = app(CreateDeliveryRunAction::class)->handle([
            'warehouse_id' => $first['warehouse']->id,
            'driver_id' => $driver->id,
            'vehicle_id' => null,
            'scheduled_date' => now()->toDateString(),
            'client_reference' => null,
            'notes' => null,
        ], $supervisor);
        $firstRunOrder = app(AssignOrderToDeliveryRunAction::class)->handle($run, $first['order'], $supervisor);
        $secondRunOrder = app(AssignOrderToDeliveryRunAction::class)->handle($run, $second['order'], $supervisor);
        app(StartDeliveryPreparationAction::class)->handle($run, $bodeguero);

        $firstItem = $firstRunOrder->items()->sole();
        $this->actingAs($bodeguero)
            ->get(route('delivery-runs.orders.preparation.edit', [$run, $firstRunOrder]))
            ->assertOk()
            ->assertSee('Solicitado')
            ->assertSee('value="0"', false)
            ->assertSee('Guardar y volver a pendientes');
        $this->actingAs($bodeguero)
            ->put(route('delivery-runs.orders.preparation.update', [$run, $firstRunOrder]), [
                'items' => [['id' => $firstItem->id, 'prepared_quantity' => '15']],
            ])
            ->assertRedirect(route('delivery-runs.show', $run).'#preparation-queue');

        $this->assertSame(DeliveryOrderStatus::Prepared, $firstRunOrder->refresh()->status);
        $this->assertSame(DeliveryOrderStatus::Pending, $secondRunOrder->refresh()->status);
        $this->actingAs($bodeguero)
            ->post(route('delivery-runs.load', $run))
            ->assertSessionHasErrors('orders');
        $this->assertDatabaseMissing('inventory_movements', [
            'delivery_run_id' => $run->id,
            'type' => InventoryMovementType::DispatchLoad->value,
        ]);

        $secondItem = $secondRunOrder->items()->sole();
        $this->actingAs($bodeguero)
            ->put(route('delivery-runs.orders.preparation.update', [$run, $secondRunOrder]), [
                'items' => [['id' => $secondItem->id, 'prepared_quantity' => '7']],
            ])
            ->assertRedirect(route('delivery-runs.show', $run).'#preparation-queue');
        $this->actingAs($bodeguero)
            ->get(route('delivery-runs.show', $run))
            ->assertOk()
            ->assertSee('Todos los pedidos están preparados')
            ->assertSee('Confirmar carga completa');
        $this->actingAs($bodeguero)->post(route('delivery-runs.load', $run))->assertRedirect();

        $this->assertSame(DeliveryRunStatus::Loaded, $run->refresh()->status);
        $this->assertSame('15.000000', $first['lines'][0]['stock']->refresh()->quantity_on_hand);
        $this->assertSame('13.000000', $second['lines'][0]['stock']->refresh()->quantity_on_hand);
    }

    public function test_dispatch_dashboard_sums_confirmed_orders_by_product_and_presentation(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $warehouse = Warehouse::query()->where('is_default', true)->sole();
        $unit = MeasurementUnit::factory()->create(['name' => 'Unidad consolidada', 'symbol' => 'uc']);
        $product = Product::factory()->for($unit, 'baseUnit')->create([
            'name' => 'Producto Consolidado X',
            'sku' => 'CON-X',
        ]);
        $presentation = ProductPresentation::factory()->for($product)->base()->create(['name' => 'Unidad']);

        foreach (['15', '7'] as $index => $quantity) {
            $order = Order::factory()->confirmed()->create([
                'warehouse_id' => $warehouse->id,
                'warehouse_code' => $warehouse->code,
                'warehouse_name' => $warehouse->name,
                'order_number' => 'PED-CONS-'.($index + 1),
                'total' => bcmul($quantity, '10', 4),
            ]);
            OrderItem::factory()->for($order)->create([
                'product_id' => $product->id,
                'product_presentation_id' => $presentation->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'presentation_name' => $presentation->name,
                'base_unit_symbol' => $unit->symbol,
                'quantity' => $quantity,
                'base_quantity' => $quantity,
                'line_total' => bcmul($quantity, '10', 4),
            ]);
        }

        $response = $this->actingAs($supervisor)->get(route('delivery-runs.index', [
            'warehouse_id' => $warehouse->id,
        ]));

        $response
            ->assertOk()
            ->assertSee('Pedidos sin asignar')
            ->assertSee('Consolidado por producto')
            ->assertSee('Producto Consolidado X');
        $this->assertSame(2, $response->viewData('pendingOrders')->total());
        $this->assertSame('22', rtrim(rtrim((string) $response->viewData('consolidated')->first()->total_base_quantity, '0'), '.'));
    }

    public function test_transfer_reference_is_optional_when_closing_a_delivery(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $driver = User::factory()->repartidor()->create();
        $context = $this->confirmedOrder([
            ['quantity' => '5', 'unit_price' => '10', 'on_hand' => '20'],
        ]);
        [$run, $runOrder] = $this->loadedRun($context['order'], $supervisor, $driver);
        app(DepartDeliveryRunAction::class)->handle($run, $driver);
        $runItem = $runOrder->items()->sole();
        app(CompleteDeliveryStopAction::class)->handle($run, $runOrder, [
            'receiver_name' => 'Cliente',
        ], [[
            'id' => $runItem->id,
            'delivered_quantity' => '5',
            'returned_quantity' => '0',
            'damaged_quantity' => '0',
            'missing_quantity' => '0',
        ]], $driver);

        $this->actingAs($driver)->post(route('delivery-runs.orders.payments.store', [$run, $runOrder]), [
            'allocations' => [
                ['method' => SettlementMethod::BankTransfer->value, 'amount' => '50', 'reference' => null],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(DeliveryOrderStatus::Delivered, $runOrder->refresh()->status);
        $this->assertSame('50.0000', $run->refresh()->transfer_total);
        $this->assertNull($runOrder->payments()->sole()->reference);
    }

    public function test_customer_returns_one_unit_and_another_is_damaged_out_of_twenty_four(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $driver = User::factory()->repartidor()->create();
        $context = $this->confirmedOrder([
            ['quantity' => '24', 'unit_price' => '10', 'on_hand' => '30'],
        ]);
        $stock = $context['lines'][0]['stock'];
        [$run, $runOrder] = $this->loadedRun($context['order'], $supervisor, $driver);
        app(DepartDeliveryRunAction::class)->handle($run, $driver);
        $runItem = $runOrder->items()->sole();
        $this->assertSame('6.000000', $stock->refresh()->quantity_on_hand);
        $this->assertSame('0.000000', $stock->quantity_reserved);
        $outcomeUrl = route('delivery-runs.orders.outcome', [$run, $runOrder]);
        $outcome = fn (string $delivered, string $returned, string $damaged, string $missing, ?string $reason): array => [
            'receiver_name' => 'Cliente',
            'outcome_reason' => $reason,
            'items' => [$runItem->id => [
                'id' => $runItem->id,
                'delivered_quantity' => $delivered,
                'returned_quantity' => $returned,
                'damaged_quantity' => $damaged,
                'missing_quantity' => $missing,
            ]],
        ];

        $this->actingAs($driver)->put($outcomeUrl, $outcome('24', '1', '1', '0', DeliveryOutcomeReason::CannotPay->value))
            ->assertSessionHasErrors('items');
        $this->actingAs($driver)->put($outcomeUrl, $outcome('22', '1', '0', '0', DeliveryOutcomeReason::CannotPay->value))
            ->assertSessionHasErrors('items');
        $this->actingAs($driver)->put($outcomeUrl, $outcome('22', '1', '1', '0', null))
            ->assertSessionHasErrors('outcome_reason');
        $this->assertSame(DeliveryOrderStatus::Loaded, $runOrder->refresh()->status);

        $this->actingAs($driver)->put($outcomeUrl, $outcome('22', '1', '1', '0', DeliveryOutcomeReason::CannotPay->value))
            ->assertSessionHasNoErrors();

        $this->assertSame(DeliveryOrderStatus::AwaitingPayment, $runOrder->refresh()->status);
        $this->assertSame(DeliveryOrderStatus::PartiallyDelivered, $runOrder->outcome_status);
        $this->assertSame('220.0000', $runOrder->delivered_total);
        $this->assertSame('220.0000', $runItem->refresh()->delivered_line_total);

        $this->actingAs($driver)->post(route('delivery-runs.orders.payments.store', [$run, $runOrder]), [
            'allocations' => [
                ['method' => SettlementMethod::Cash->value, 'amount' => '240', 'reference' => null],
            ],
        ])->assertSessionHasErrors('allocations');
        $this->actingAs($driver)->post(route('delivery-runs.orders.payments.store', [$run, $runOrder]), [
            'allocations' => [
                ['method' => SettlementMethod::Cash->value, 'amount' => '220', 'reference' => null],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(DeliveryOrderStatus::PartiallyDelivered, $runOrder->refresh()->status);
        $this->assertSame(OrderStatus::PartiallyDelivered, $context['order']->refresh()->status);
        $this->assertSame('0.0000', $runOrder->balance_due);
        $this->assertSame('6.000000', $stock->refresh()->quantity_on_hand);

        app(SettleDeliveryRunAction::class)->handle($run, ['cash_declared' => '220', 'settlement_notes' => null], $supervisor);

        $this->assertSame(DeliveryRunStatus::Settled, $run->refresh()->status);
        $this->assertSame('220.0000', $run->delivered_total);
        $this->assertSame('220.0000', $run->cash_expected);
        $this->assertSame('7.000000', $stock->refresh()->quantity_on_hand);
        $this->assertSame('0.000000', $stock->quantity_reserved);
        $this->assertDatabaseHas('inventory_movements', [
            'delivery_run_item_id' => $runItem->id,
            'type' => InventoryMovementType::DispatchReturn->value,
        ]);
    }

    public function test_nothing_delivered_needs_no_payment_and_returns_the_full_load_to_stock(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $driver = User::factory()->repartidor()->create();
        $context = $this->confirmedOrder([
            ['quantity' => '24', 'unit_price' => '10', 'on_hand' => '30'],
        ]);
        $stock = $context['lines'][0]['stock'];
        [$run, $runOrder] = $this->loadedRun($context['order'], $supervisor, $driver);
        app(DepartDeliveryRunAction::class)->handle($run, $driver);
        $runItem = $runOrder->items()->sole();

        $this->actingAs($driver)->put(route('delivery-runs.orders.outcome', [$run, $runOrder]), [
            'outcome_reason' => DeliveryOutcomeReason::BusinessClosed->value,
            'items' => [$runItem->id => [
                'id' => $runItem->id,
                'delivered_quantity' => '0',
                'returned_quantity' => '24',
                'damaged_quantity' => '0',
                'missing_quantity' => '0',
            ]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(DeliveryOrderStatus::NotDelivered, $runOrder->refresh()->status);
        $this->assertSame('0.0000', $runOrder->delivered_total);
        $this->assertSame(DeliveryRunStatus::AwaitingSettlement, $run->refresh()->status);
        $this->assertDatabaseCount('delivery_settlements', 0);

        app(SettleDeliveryRunAction::class)->handle($run, ['cash_declared' => '0', 'settlement_notes' => null], $supervisor);

        $this->assertSame('30.000000', $stock->refresh()->quantity_on_hand);
        $this->assertSame('0.000000', $stock->quantity_reserved);
        $this->assertSame(OrderStatus::NotDelivered, $context['order']->refresh()->status);
    }

    public function test_damaged_and_missing_units_are_not_charged_and_do_not_return_to_stock(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $driver = User::factory()->repartidor()->create();
        $context = $this->confirmedOrder([
            ['quantity' => '24', 'unit_price' => '10', 'on_hand' => '30'],
        ]);
        $stock = $context['lines'][0]['stock'];
        [$run, $runOrder] = $this->loadedRun($context['order'], $supervisor, $driver);
        app(DepartDeliveryRunAction::class)->handle($run, $driver);
        $runItem = $runOrder->items()->sole();

        $this->actingAs($driver)->put(route('delivery-runs.orders.outcome', [$run, $runOrder]), [
            'receiver_name' => 'Cliente',
            'items' => [$runItem->id => [
                'id' => $runItem->id,
                'delivered_quantity' => '21',
                'returned_quantity' => '0',
                'damaged_quantity' => '2',
                'missing_quantity' => '1',
            ]],
        ])->assertSessionHasNoErrors();
        $this->actingAs($driver)->post(route('delivery-runs.orders.payments.store', [$run, $runOrder]), [
            'allocations' => [
                ['method' => SettlementMethod::Cash->value, 'amount' => '210', 'reference' => null],
            ],
        ])->assertSessionHasNoErrors();
        app(SettleDeliveryRunAction::class)->handle($run, ['cash_declared' => '210', 'settlement_notes' => null], $supervisor);

        $this->assertSame('210.0000', $run->refresh()->delivered_total);
        $this->assertSame('6.000000', $stock->refresh()->quantity_on_hand);
        $this->assertDatabaseMissing('inventory_movements', [
            'delivery_run_item_id' => $runItem->id,
            'type' => InventoryMovementType::DispatchReturn->value,
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'delivery_run_item_id' => $runItem->id,
            'type' => InventoryMovementType::DispatchDamage->value,
            'presentation_quantity' => '2.000000',
            'quantity_on_hand_delta' => '0.000000',
            'quantity_on_hand_after' => '6.000000',
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'delivery_run_item_id' => $runItem->id,
            'type' => InventoryMovementType::DispatchMissing->value,
            'presentation_quantity' => '1.000000',
        ]);
        $this->assertSame(DeliveryOutcomeReason::DamagedGoods, $runOrder->refresh()->outcome_reason);
    }

    public function test_driver_can_correct_a_saved_outcome_before_registering_the_payment(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $driver = User::factory()->repartidor()->create();
        $context = $this->confirmedOrder([
            ['quantity' => '24', 'unit_price' => '10', 'on_hand' => '30'],
        ]);
        [$run, $runOrder] = $this->loadedRun($context['order'], $supervisor, $driver);
        app(DepartDeliveryRunAction::class)->handle($run, $driver);
        $runItem = $runOrder->items()->sole();
        $outcomeUrl = route('delivery-runs.orders.outcome', [$run, $runOrder]);
        $reopenUrl = route('delivery-runs.orders.outcome.reopen', [$run, $runOrder]);
        $items = fn (string $delivered, string $returned): array => [$runItem->id => [
            'id' => $runItem->id,
            'delivered_quantity' => $delivered,
            'returned_quantity' => $returned,
            'damaged_quantity' => '0',
            'missing_quantity' => '0',
        ]];

        $this->actingAs($driver)->post($reopenUrl)->assertSessionHasErrors('delivery_run_order');

        $this->actingAs($driver)->put($outcomeUrl, [
            'receiver_name' => 'Cliente',
            'outcome_reason' => DeliveryOutcomeReason::CannotPay->value,
            'items' => $items('20', '4'),
        ])->assertSessionHasNoErrors();
        $this->assertSame('200.0000', $runOrder->refresh()->delivered_total);

        $this->actingAs(User::factory()->repartidor()->create())->post($reopenUrl)->assertForbidden();
        $this->actingAs($driver)->post($reopenUrl)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('delivery-runs.show', $run).'#stop-'.$runOrder->id);

        $this->assertSame(DeliveryOrderStatus::Loaded, $runOrder->refresh()->status);
        $this->assertNull($runOrder->outcome_status);
        $this->assertSame('0.0000', $runOrder->delivered_total);
        $this->assertSame('0.0000', $run->refresh()->delivered_total);
        $this->actingAs($driver)->get(route('delivery-runs.show', $run))
            ->assertOk()
            ->assertSee('name="items['.$runItem->id.'][delivered_quantity]" type="number" inputmode="decimal" min="0" max="24" step="1" required value="20"', false);

        $this->actingAs($driver)->put($outcomeUrl, [
            'receiver_name' => 'Cliente',
            'outcome_reason' => DeliveryOutcomeReason::CannotPay->value,
            'items' => $items('23', '1'),
        ])->assertSessionHasNoErrors();
        $this->assertSame('230.0000', $runOrder->refresh()->delivered_total);

        $this->actingAs($driver)->post(route('delivery-runs.orders.payments.store', [$run, $runOrder]), [
            'allocations' => [
                ['method' => SettlementMethod::Cash->value, 'amount' => '230', 'reference' => null],
            ],
        ])->assertSessionHasNoErrors();
        $this->actingAs($driver)->post($reopenUrl)->assertSessionHasErrors('delivery_run_order');
        $this->assertSame(DeliveryOrderStatus::PartiallyDelivered, $runOrder->refresh()->status);
    }

    public function test_a_not_delivered_stop_can_be_reopened_until_the_run_is_settled(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $driver = User::factory()->repartidor()->create();
        $context = $this->confirmedOrder([
            ['quantity' => '4', 'unit_price' => '10', 'on_hand' => '10'],
        ]);
        [$run, $runOrder] = $this->loadedRun($context['order'], $supervisor, $driver);
        app(DepartDeliveryRunAction::class)->handle($run, $driver);
        $runItem = $runOrder->items()->sole();
        $reopenUrl = route('delivery-runs.orders.outcome.reopen', [$run, $runOrder]);

        $this->actingAs($driver)->put(route('delivery-runs.orders.outcome', [$run, $runOrder]), [
            'outcome_reason' => DeliveryOutcomeReason::BusinessClosed->value,
            'items' => [$runItem->id => [
                'id' => $runItem->id,
                'delivered_quantity' => '0',
                'returned_quantity' => '4',
                'damaged_quantity' => '0',
                'missing_quantity' => '0',
            ]],
        ])->assertSessionHasNoErrors();
        $this->assertSame(DeliveryRunStatus::AwaitingSettlement, $run->refresh()->status);
        $this->assertSame(OrderStatus::NotDelivered, $context['order']->refresh()->status);

        $this->actingAs($driver)->post($reopenUrl)->assertSessionHasNoErrors();

        $this->assertSame(DeliveryOrderStatus::Loaded, $runOrder->refresh()->status);
        $this->assertSame(DeliveryRunStatus::InTransit, $run->refresh()->status);
        $this->assertSame(OrderStatus::InTransit, $context['order']->refresh()->status);

        app(CompleteDeliveryStopAction::class)->handle($run, $runOrder, [
            'outcome_reason' => DeliveryOutcomeReason::BusinessClosed->value,
        ], [[
            'id' => $runItem->id,
            'delivered_quantity' => '0',
            'returned_quantity' => '4',
            'damaged_quantity' => '0',
            'missing_quantity' => '0',
        ]], $driver);
        app(SettleDeliveryRunAction::class)->handle($run->refresh(), ['cash_declared' => '0', 'settlement_notes' => null], $supervisor);

        $this->actingAs($supervisor)->post($reopenUrl)->assertForbidden();
    }

    /**
     * @param  list<array{quantity: string, unit_price: string, on_hand: string}>  $lineDefinitions
     * @return array{order: Order, warehouse: Warehouse, salesperson: User, lines: list<array{product: Product, presentation: ProductPresentation, item: OrderItem, stock: InventoryStock}>}
     */
    private function confirmedOrder(
        array $lineDefinitions,
        PaymentTerm $paymentTerm = PaymentTerm::Cash,
    ): array {
        $salesperson = User::factory()->preventista()->create();
        $warehouse = Warehouse::query()->where('is_default', true)->sole();
        $order = Order::factory()->create([
            'warehouse_id' => $warehouse->id,
            'warehouse_code' => $warehouse->code,
            'warehouse_name' => $warehouse->name,
            'salesperson_id' => $salesperson->id,
            'created_by' => $salesperson->id,
            'salesperson_name' => $salesperson->name,
            'payment_term' => $paymentTerm,
            'status' => OrderStatus::Draft,
        ]);
        CustomerCreditProfile::factory()->create([
            'customer_id' => $order->customer_id,
            'credit_limit' => '100000.0000',
            'term_days' => 30,
        ]);
        $lines = [];

        foreach ($lineDefinitions as $definition) {
            $unit = MeasurementUnit::factory()->create([
                'name' => fake()->unique()->bothify('Unidad-####-????'),
                'symbol' => fake()->unique()->lexify('u?'),
            ]);
            $product = Product::factory()->for($unit, 'baseUnit')->create();
            $presentation = ProductPresentation::factory()->for($product)->base()->create([
                'name' => 'Unidad',
                'conversion_factor' => '1.000000',
                'sale_price' => $definition['unit_price'],
            ]);
            $stock = InventoryStock::query()->firstOrCreate(
                ['warehouse_id' => $warehouse->id, 'product_id' => $product->id],
                ['quantity_on_hand' => '0', 'quantity_reserved' => '0', 'reorder_point' => '0'],
            );
            $stock->update([
                'quantity_on_hand' => $definition['on_hand'],
                'quantity_reserved' => '0',
            ]);
            $item = OrderItem::query()->create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_presentation_id' => $presentation->id,
                'price_tier_id' => null,
                'product_sku' => $product->sku,
                'product_name' => $product->name,
                'presentation_name' => $presentation->name,
                'base_unit_symbol' => $unit->symbol,
                'conversion_factor' => '1.000000',
                'quantity' => $definition['quantity'],
                'base_quantity' => $definition['quantity'],
                'standard_unit_price' => $definition['unit_price'],
                'unit_price' => $definition['unit_price'],
                'price_source' => OrderPriceSource::Presentation,
                'line_total' => bcmul($definition['quantity'], $definition['unit_price'], 4),
            ]);
            $lines[] = compact('product', 'presentation', 'item', 'stock');
        }

        app(ConfirmOrderAction::class)->handle($order, $salesperson);

        return [
            'order' => $order->refresh(),
            'warehouse' => $warehouse,
            'salesperson' => $salesperson,
            'lines' => $lines,
        ];
    }

    /** @return array{DeliveryRun, DeliveryRunOrder} */
    private function assignedRun(Order $order, User $supervisor, User $driver): array
    {
        $run = app(CreateDeliveryRunAction::class)->handle([
            'warehouse_id' => $order->warehouse_id,
            'driver_id' => $driver->id,
            'vehicle_id' => null,
            'scheduled_date' => now()->toDateString(),
            'client_reference' => null,
            'notes' => null,
        ], $supervisor);
        $runOrder = app(AssignOrderToDeliveryRunAction::class)->handle($run, $order, $supervisor);

        return [$run, $runOrder];
    }

    /** @return array{DeliveryRun, DeliveryRunOrder} */
    private function loadedRun(Order $order, User $supervisor, User $driver): array
    {
        [$run, $runOrder] = $this->assignedRun($order, $supervisor, $driver);
        app(StartDeliveryPreparationAction::class)->handle($run, $supervisor);
        $items = $runOrder->items()->get();
        app(SaveDeliveryPreparationAction::class)->handle($run, $items->map(fn ($item): array => [
            'id' => $item->id,
            'prepared_quantity' => $item->requested_quantity,
        ])->all());
        app(ConfirmDeliveryLoadAction::class)->handle($run, $supervisor);

        return [$run->refresh(), $runOrder->refresh()];
    }
}
