<?php

namespace Tests\Feature\Api\V1;

use App\Actions\AssignOrderToDeliveryRunAction;
use App\Actions\CompleteDeliveryStopAction;
use App\Actions\ConfirmDeliveryLoadAction;
use App\Actions\ConfirmOrderAction;
use App\Actions\CreateDeliveryRunAction;
use App\Actions\DepartDeliveryRunAction;
use App\Actions\SaveDeliveryPreparationAction;
use App\Actions\StartDeliveryPreparationAction;
use App\DeliveryOrderStatus;
use App\DeliveryPaymentStatus;
use App\DeliveryRunStatus;
use App\Models\DeliveryPayment;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunOrder;
use App\Models\InventoryStock;
use App\Models\MeasurementUnit;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\User;
use App\Models\Warehouse;
use App\OrderPriceSource;
use App\OrderStatus;
use App\PaymentMethod;
use App\PaymentTerm;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeliveryApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_delivery_endpoints_require_authentication_ability_role_and_assignment(): void
    {
        $this->getJson('/api/v1/delivery-runs')->assertUnauthorized();

        $supervisor = User::factory()->supervisor()->create();
        Sanctum::actingAs($supervisor, ['catalog:manage']);
        $this->getJson('/api/v1/delivery-runs')->assertForbidden();

        $preventista = User::factory()->preventista()->create();
        Sanctum::actingAs($preventista, ['deliveries:view']);
        $this->getJson('/api/v1/delivery-runs')->assertForbidden();

        $firstDriver = User::factory()->repartidor()->create();
        $secondDriver = User::factory()->repartidor()->create();
        $warehouse = Warehouse::query()->where('is_default', true)->sole();
        $firstRun = DeliveryRun::factory()->create([
            'warehouse_id' => $warehouse->id,
            'driver_id' => $firstDriver->id,
            'created_by' => $supervisor->id,
            'warehouse_code' => $warehouse->code,
            'warehouse_name' => $warehouse->name,
            'driver_name' => $firstDriver->name,
        ]);
        $secondRun = DeliveryRun::factory()->create([
            'warehouse_id' => $warehouse->id,
            'driver_id' => $secondDriver->id,
            'created_by' => $supervisor->id,
            'warehouse_code' => $warehouse->code,
            'warehouse_name' => $warehouse->name,
            'driver_name' => $secondDriver->name,
        ]);

        Sanctum::actingAs($firstDriver, ['deliveries:view']);
        $this->getJson('/api/v1/delivery-runs')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $firstRun->id);
        $this->getJson("/api/v1/delivery-runs/{$firstRun->id}")->assertOk();
        $this->getJson("/api/v1/delivery-runs/{$secondRun->id}")->assertForbidden();

        Sanctum::actingAs($firstDriver, ['deliveries:manage']);
        $this->postJson('/api/v1/delivery-runs', [
            'warehouse_id' => $warehouse->id,
            'driver_id' => $firstDriver->id,
            'scheduled_date' => now()->toDateString(),
        ])->assertForbidden();
    }

    public function test_api_supports_the_full_handoff_from_supervisor_to_warehouse_driver_and_settlement(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $bodeguero = User::factory()->create();
        $driver = User::factory()->repartidor()->create();
        $context = $this->confirmedOrder('3', '10', '15');

        Sanctum::actingAs($supervisor, ['deliveries:manage']);
        $create = $this->postJson('/api/v1/delivery-runs', [
            'warehouse_id' => $context['warehouse']->id,
            'driver_id' => $driver->id,
            'vehicle_id' => null,
            'scheduled_date' => now()->toDateString(),
            'client_reference' => 'MOBILE-RUN-001',
            'notes' => null,
        ])->assertCreated()
            ->assertJsonPath('data.status', DeliveryRunStatus::Draft->value)
            ->assertJsonPath('data.driver.id', $driver->id);
        $runId = $create->json('data.id');
        $run = DeliveryRun::query()->findOrFail($runId);

        $assign = $this->postJson("/api/v1/delivery-runs/{$runId}/orders", [
            'order_id' => $context['order']->id,
            'visit_order' => 1,
        ])->assertCreated()
            ->assertJsonPath('data.status', DeliveryOrderStatus::Pending->value)
            ->assertJsonPath('data.order.id', $context['order']->id);
        $runOrder = DeliveryRunOrder::query()->findOrFail($assign->json('data.id'));
        $runItem = $runOrder->items()->sole();

        Sanctum::actingAs($bodeguero, ['deliveries:prepare']);
        $this->postJson("/api/v1/delivery-runs/{$runId}/preparation")
            ->assertOk()
            ->assertJsonPath('data.status', DeliveryRunStatus::Preparing->value);
        $this->putJson("/api/v1/delivery-runs/{$runId}/preparation", [
            'items' => [['id' => $runItem->id, 'prepared_quantity' => '3']],
        ])->assertOk()
            ->assertJsonPath('data.orders.0.items.0.prepared_quantity', '3.000000');
        $this->postJson("/api/v1/delivery-runs/{$runId}/load")
            ->assertOk()
            ->assertJsonPath('data.status', DeliveryRunStatus::Loaded->value)
            ->assertJsonPath('data.orders.0.items.0.loaded_quantity', '3.000000');

        Sanctum::actingAs($driver, ['deliveries:execute']);
        $this->postJson("/api/v1/delivery-runs/{$runId}/depart")
            ->assertOk()
            ->assertJsonPath('data.status', DeliveryRunStatus::InTransit->value);
        $this->putJson("/api/v1/delivery-runs/{$runId}/orders/{$runOrder->id}/outcome", [
            'receiver_name' => 'José Pérez',
            'outcome_reason' => null,
            'items' => [[
                'id' => $runItem->id,
                'delivered_quantity' => '3',
                'returned_quantity' => '0',
                'damaged_quantity' => '0',
                'missing_quantity' => '0',
            ]],
        ])->assertOk()
            ->assertJsonPath('data.status', DeliveryOrderStatus::Delivered->value)
            ->assertJsonPath('data.delivered_total', '30.0000');
        $this->postJson("/api/v1/delivery-runs/{$runId}/orders/{$runOrder->id}/payments", [
            'client_reference' => 'MOBILE-PAY-001',
            'method' => PaymentMethod::Cash->value,
            'amount' => '30',
        ])->assertCreated()
            ->assertJsonPath('data.status', DeliveryPaymentStatus::Active->value)
            ->assertJsonPath('data.amount', '30.0000');

        Sanctum::actingAs($supervisor, ['deliveries:settle']);
        $this->postJson("/api/v1/delivery-runs/{$runId}/settle", [
            'cash_declared' => '30',
            'settlement_notes' => null,
        ])->assertOk()
            ->assertJsonPath('data.status', DeliveryRunStatus::Settled->value)
            ->assertJsonPath('data.financials.collected_total', '30.0000')
            ->assertJsonPath('data.financials.cash_difference', '0.0000');

        $this->assertSame(OrderStatus::Delivered, $context['order']->refresh()->status);
        $this->assertSame('12.000000', $context['stock']->refresh()->quantity_on_hand);
        $this->assertSame('0.000000', $context['stock']->quantity_reserved);
    }

    public function test_mobile_payment_is_idempotent_validated_and_voidable_only_by_supervision(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $driver = User::factory()->repartidor()->create();
        $context = $this->confirmedOrder('2', '10', '10');
        [$run, $runOrder] = $this->completedRun($context['order'], $supervisor, $driver);

        Sanctum::actingAs($driver, ['deliveries:execute']);
        $baseUrl = "/api/v1/delivery-runs/{$run->id}/orders/{$runOrder->id}/payments";
        $this->postJson($baseUrl, [
            'method' => PaymentMethod::BankTransfer->value,
            'amount' => '10',
            'reference' => null,
        ])->assertUnprocessable()->assertJsonValidationErrors('reference');
        $this->postJson($baseUrl, [
            'method' => PaymentMethod::Cash->value,
            'amount' => '21',
        ])->assertUnprocessable()->assertJsonValidationErrors('amount');

        $payload = [
            'client_reference' => 'OFFLINE-PAYMENT-ABC',
            'method' => PaymentMethod::Cash->value,
            'amount' => '10',
            'reference' => null,
        ];
        $first = $this->postJson($baseUrl, $payload)->assertCreated();
        $second = $this->postJson($baseUrl, $payload)->assertCreated();
        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('delivery_payments', 1);

        $this->postJson($baseUrl, [...$payload, 'amount' => '9'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('client_reference');
        $payment = DeliveryPayment::query()->sole();

        $this->postJson("{$baseUrl}/{$payment->id}/void", [
            'reason' => 'Prueba de permiso',
        ])->assertForbidden();

        Sanctum::actingAs($supervisor, ['deliveries:settle']);
        $this->postJson("{$baseUrl}/{$payment->id}/void", [
            'reason' => 'Cobro duplicado reportado por el cliente.',
        ])->assertOk()
            ->assertJsonPath('data.status', DeliveryPaymentStatus::Voided->value);

        $this->assertSame('0.0000', $run->refresh()->collected_total);
        $this->assertSame('20.0000', $run->credit_total);
    }

    /**
     * @return array{order: Order, warehouse: Warehouse, stock: InventoryStock}
     */
    private function confirmedOrder(string $quantity, string $unitPrice, string $onHand): array
    {
        $salesperson = User::factory()->preventista()->create();
        $warehouse = Warehouse::query()->where('is_default', true)->sole();
        $unit = MeasurementUnit::factory()->create([
            'name' => fake()->unique()->bothify('Unidad-####-????'),
            'symbol' => fake()->unique()->lexify('u?'),
        ]);
        $product = Product::factory()->for($unit, 'baseUnit')->create();
        $presentation = ProductPresentation::factory()->for($product)->base()->create([
            'name' => 'Unidad',
            'conversion_factor' => '1.000000',
            'sale_price' => $unitPrice,
        ]);
        $stock = InventoryStock::query()->firstOrCreate(
            ['warehouse_id' => $warehouse->id, 'product_id' => $product->id],
            ['quantity_on_hand' => '0', 'quantity_reserved' => '0', 'reorder_point' => '0'],
        );
        $stock->update(['quantity_on_hand' => $onHand, 'quantity_reserved' => '0']);
        $order = Order::factory()->create([
            'warehouse_id' => $warehouse->id,
            'warehouse_code' => $warehouse->code,
            'warehouse_name' => $warehouse->name,
            'salesperson_id' => $salesperson->id,
            'created_by' => $salesperson->id,
            'salesperson_name' => $salesperson->name,
            'payment_term' => PaymentTerm::Cash,
            'status' => OrderStatus::Draft,
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_presentation_id' => $presentation->id,
            'price_tier_id' => null,
            'product_sku' => $product->sku,
            'product_name' => $product->name,
            'presentation_name' => $presentation->name,
            'base_unit_symbol' => $unit->symbol,
            'conversion_factor' => '1.000000',
            'quantity' => $quantity,
            'base_quantity' => $quantity,
            'standard_unit_price' => $unitPrice,
            'unit_price' => $unitPrice,
            'price_source' => OrderPriceSource::Presentation,
            'line_total' => bcmul($quantity, $unitPrice, 4),
        ]);
        app(ConfirmOrderAction::class)->handle($order, $salesperson);

        return compact('order', 'warehouse', 'stock');
    }

    /** @return array{DeliveryRun, DeliveryRunOrder} */
    private function completedRun(Order $order, User $supervisor, User $driver): array
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
        app(StartDeliveryPreparationAction::class)->handle($run, $supervisor);
        $runItem = $runOrder->items()->sole();
        app(SaveDeliveryPreparationAction::class)->handle($run, [[
            'id' => $runItem->id,
            'prepared_quantity' => $runItem->requested_quantity,
        ]]);
        app(ConfirmDeliveryLoadAction::class)->handle($run, $supervisor);
        app(DepartDeliveryRunAction::class)->handle($run, $driver);
        app(CompleteDeliveryStopAction::class)->handle($run, $runOrder, [
            'receiver_name' => 'Cliente',
        ], [[
            'id' => $runItem->id,
            'delivered_quantity' => $runItem->requested_quantity,
            'returned_quantity' => '0',
            'damaged_quantity' => '0',
            'missing_quantity' => '0',
        ]], $driver);

        return [$run->refresh(), $runOrder->refresh()];
    }
}
