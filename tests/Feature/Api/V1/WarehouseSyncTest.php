<?php

namespace Tests\Feature\Api\V1;

use App\Actions\ApplyInventoryDeltaAction;
use App\Actions\ConfirmOrderAction;
use App\DeliveryRunStatus;
use App\InventoryCountStatus;
use App\InventoryDocumentStatus;
use App\InventoryMovementType;
use App\Models\CustomerCreditProfile;
use App\Models\DeliveryRun;
use App\Models\InventoryCount;
use App\Models\InventoryDocument;
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
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WarehouseSyncTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_warehouse_phone_downloads_what_it_assembles_runs_from_and_can_send_its_operations(): void
    {
        $order = $this->confirmedOrder('6');
        $draft = DeliveryRun::factory()->create(['status' => DeliveryRunStatus::Draft]);
        $keeper = User::factory()->bodeguero()->create();
        Sanctum::actingAs($keeper, $keeper->apiAbilities());

        $this->getJson('/api/v1/sync')
            ->assertOk()
            ->assertJsonPath('resources.dispatch_orders.available', true)
            ->assertJsonPath('resources.drivers.available', true)
            ->assertJsonPath('resources.warehouses.available', true)
            ->assertJsonPath('resources.delivery_runs.available', true)
            ->assertJsonPath('resources.customers.available', false)
            ->assertJsonFragment(['operations' => [
                'delivery.create', 'delivery.assign', 'delivery.unassign',
                'delivery.prepare_start', 'delivery.prepare', 'delivery.load',
                'inventory.product_count', 'inventory.receipt',
            ]]);
        $this->getJson('/api/v1/sync/pull/dispatch_orders')
            ->assertOk()
            ->assertJsonPath('records.0.id', $order->id)
            ->assertJsonPath('records.0.items.0.quantity', '6.000000');
        $this->assertContains($draft->id, array_column($this->getJson('/api/v1/sync/pull/delivery_runs')->json('records'), 'id'), 'the warehouse sees runs it is still assembling');

        $driver = User::factory()->repartidor()->create();
        Sanctum::actingAs($driver, $driver->apiAbilities());
        $this->assertNotContains($draft->id, array_column($this->getJson('/api/v1/sync/pull/delivery_runs')->json('records'), 'id'), 'a driver never gets a draft');

        $supervisor = User::factory()->supervisor()->create();
        Sanctum::actingAs($supervisor, $supervisor->apiAbilities());
        $this->assertContains('delivery.settle', $this->getJson('/api/v1/sync')->json('operations'));
    }

    public function test_a_run_assembled_prepared_and_loaded_without_signal_is_applied_once(): void
    {
        $order = $this->confirmedOrder('6');
        $keeper = User::factory()->bodeguero()->create();
        $driver = User::factory()->repartidor()->create();
        $runId = (string) Str::ulid();
        $line = $order->items()->sole();
        Sanctum::actingAs($keeper, $keeper->apiAbilities());
        $queue = [
            ['client_reference' => 'B001:1', 'type' => 'delivery.create', 'payload' => ['id' => $runId, 'warehouse_id' => $order->warehouse_id, 'driver_id' => $driver->id, 'scheduled_date' => today()->toDateString()]],
            ['client_reference' => 'B001:2', 'type' => 'delivery.assign', 'payload' => ['delivery_run_id' => $runId, 'order_id' => $order->id]],
            ['client_reference' => 'B001:3', 'type' => 'delivery.prepare_start', 'payload' => ['delivery_run_id' => $runId]],
            // Prepared by order line: the phone never saw the run lines the server created.
            ['client_reference' => 'B001:4', 'type' => 'delivery.prepare', 'payload' => ['delivery_run_id' => $runId, 'items' => [['order_item_id' => $line->id, 'prepared_quantity' => '6']]]],
            ['client_reference' => 'B001:5', 'type' => 'delivery.load', 'payload' => ['delivery_run_id' => $runId]],
        ];

        $first = $this->postJson('/api/v1/sync/push', ['operations' => $queue])->assertOk()->json('results');
        $again = $this->postJson('/api/v1/sync/push', ['operations' => $queue])->assertOk()->json('results');

        $this->assertSame(array_fill(0, 5, 'accepted'), array_column($first, 'status'), json_encode($first));
        $this->assertSame(array_fill(0, 5, 'duplicate'), array_column($again, 'status'));
        $run = DeliveryRun::query()->findOrFail($runId);
        $this->assertSame(DeliveryRunStatus::Loaded, $run->status);
        $this->assertSame($keeper->id, (int) $run->preparation_started_by);
        $this->assertSame('6.000000', $run->runOrders()->sole()->items()->sole()->loaded_quantity);
    }

    public function test_only_the_keeper_who_started_a_preparation_continues_it_and_a_supervisor_may_take_over(): void
    {
        $order = $this->confirmedOrder('6');
        $keeper = User::factory()->bodeguero()->create(['name' => 'Rosa Bodega']);
        $other = User::factory()->bodeguero()->create();
        $supervisor = User::factory()->supervisor()->create();
        $runId = (string) Str::ulid();
        Sanctum::actingAs($keeper, $keeper->apiAbilities());
        $this->postJson('/api/v1/sync/push', ['operations' => [
            ['client_reference' => 'B001:1', 'type' => 'delivery.create', 'payload' => ['id' => $runId, 'warehouse_id' => $order->warehouse_id, 'driver_id' => User::factory()->repartidor()->create()->id, 'scheduled_date' => today()->toDateString()]],
            ['client_reference' => 'B001:2', 'type' => 'delivery.assign', 'payload' => ['delivery_run_id' => $runId, 'order_id' => $order->id]],
            ['client_reference' => 'B001:3', 'type' => 'delivery.prepare_start', 'payload' => ['delivery_run_id' => $runId]],
        ]])->assertOk();
        $prepare = ['delivery_run_id' => $runId, 'items' => [['order_item_id' => $order->items()->sole()->id, 'prepared_quantity' => '6']]];

        Sanctum::actingAs($other, $other->apiAbilities());
        $rejected = $this->postJson('/api/v1/sync/push', ['operations' => [
            ['client_reference' => 'B002:1', 'type' => 'delivery.prepare', 'payload' => $prepare],
        ]])->assertOk()->json('results.0');
        $this->assertSame('rejected', $rejected['status']);
        $this->assertStringContainsString('la está preparando Rosa Bodega', $rejected['message']);

        Sanctum::actingAs($supervisor, $supervisor->apiAbilities());
        $this->postJson('/api/v1/sync/push', ['operations' => [
            ['client_reference' => 'S001:1', 'type' => 'delivery.prepare', 'payload' => $prepare],
        ]])->assertOk()->assertJsonPath('results.0.status', 'accepted');
    }

    public function test_a_product_counted_hours_ago_is_compared_with_the_stock_at_that_moment(): void
    {
        $warehouse = Warehouse::query()->where('is_default', true)->sole();
        [$product] = $this->product('20');
        $keeper = User::factory()->bodeguero()->create(['name' => 'Rosa Bodega']);
        $supervisor = User::factory()->supervisor()->create();
        // 5 went out an hour after the count, before the phone got signal.
        app(ApplyInventoryDeltaAction::class)->handle($warehouse, $product, InventoryMovementType::AdjustmentOut, '-5', '0', $supervisor, ['occurred_at' => now()->subHour()]);
        Sanctum::actingAs($keeper, $keeper->apiAbilities());

        $result = $this->postJson('/api/v1/sync/push', ['operations' => [
            ['client_reference' => 'B001:9', 'type' => 'inventory.product_count', 'payload' => [
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'counted_quantity' => '18',
                'counted_at' => now()->subHours(2)->toIso8601String(),
                'notes' => 'Dos bolsas rotas',
            ]],
        ]])->assertOk()->json('results.0');

        $this->assertSame('accepted', $result['status'], json_encode($result));
        $count = InventoryCount::query()->with('items')->sole();
        $this->assertSame(InventoryCountStatus::Posted, $count->status);
        $this->assertSame('20.000000', $count->items->sole()->expected_quantity, 'what there was when it was counted, not the 15 of now');
        $this->assertSame('-2.000000', $count->items->sole()->difference);
        $this->assertSame('13.000000', InventoryStock::query()->where('product_id', $product->id)->value('quantity_on_hand'));
        $this->assertStringContainsString('Rosa Bodega contó', $supervisor->notifications()->sole()->data['message']);
    }

    public function test_a_receipt_with_its_lines_is_applied_in_one_operation(): void
    {
        $warehouse = Warehouse::query()->where('is_default', true)->sole();
        [$product, $presentation] = $this->product('4');
        $keeper = User::factory()->bodeguero()->create();
        Sanctum::actingAs($keeper, $keeper->apiAbilities());

        $result = $this->postJson('/api/v1/sync/push', ['operations' => [
            ['client_reference' => 'B001:7', 'type' => 'inventory.receipt', 'payload' => [
                'warehouse_id' => $warehouse->id,
                'type' => 'receipt',
                'occurred_on' => today()->toDateString(),
                'external_reference' => 'FAC-889',
                'items' => [['product_presentation_id' => $presentation->id, 'quantity' => '10', 'unit_cost' => '12.5']],
            ]],
        ]])->assertOk()->json('results.0');

        $this->assertSame('accepted', $result['status'], json_encode($result));
        $this->assertSame(InventoryDocumentStatus::Posted, InventoryDocument::query()->sole()->status);
        $this->assertSame('14.000000', InventoryStock::query()->where('product_id', $product->id)->value('quantity_on_hand'));
    }

    /** @return array{0: Product, 1: ProductPresentation} */
    private function product(string $onHand): array
    {
        $unit = MeasurementUnit::factory()->create(['name' => fake()->unique()->word(), 'symbol' => fake()->unique()->lexify('u??')]);
        $product = Product::factory()->for($unit, 'baseUnit')->create();
        $presentation = ProductPresentation::factory()->for($product)->base()->create(['name' => 'Unidad', 'sale_price' => '10']);
        InventoryStock::query()->create([
            'warehouse_id' => Warehouse::query()->where('is_default', true)->value('id'),
            'product_id' => $product->id,
            'quantity_on_hand' => $onHand,
            'quantity_reserved' => '0',
            'reorder_point' => '0',
        ]);

        return [$product, $presentation];
    }

    private function confirmedOrder(string $quantity): Order
    {
        $salesperson = User::factory()->preventista()->create();
        $warehouse = Warehouse::query()->where('is_default', true)->sole();
        [$product, $presentation] = $this->product('50');
        $order = Order::factory()->create([
            'warehouse_id' => $warehouse->id,
            'warehouse_code' => $warehouse->code,
            'warehouse_name' => $warehouse->name,
            'salesperson_id' => $salesperson->id,
            'created_by' => $salesperson->id,
            'salesperson_name' => $salesperson->name,
            'status' => OrderStatus::Draft,
        ]);
        CustomerCreditProfile::factory()->create(['customer_id' => $order->customer_id, 'credit_limit' => '100000.0000', 'term_days' => 30]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_presentation_id' => $presentation->id,
            'product_sku' => $product->sku,
            'product_name' => $product->name,
            'presentation_name' => $presentation->name,
            'base_unit_symbol' => $product->baseUnit->symbol,
            'conversion_factor' => '1.000000',
            'quantity' => $quantity,
            'base_quantity' => $quantity,
            'standard_unit_price' => '10',
            'unit_price' => '10',
            'price_source' => OrderPriceSource::Presentation,
            'line_total' => bcmul($quantity, '10', 4),
        ]);
        app(ConfirmOrderAction::class)->handle($order, $salesperson);

        return $order->refresh();
    }
}
