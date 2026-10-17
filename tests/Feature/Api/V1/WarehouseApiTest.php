<?php

namespace Tests\Feature\Api\V1;

use App\Actions\ApplyInventoryDeltaAction;
use App\Actions\ConfirmOrderAction;
use App\DeliveryOrderStatus;
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
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** The warehouse phone works online: every step goes straight to the API. */
class WarehouseApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_warehouse_reads_its_work_live_and_cannot_queue_it_offline(): void
    {
        [, $presentation] = $this->product('50');
        $order = $this->order([[$presentation, '6']]);
        $draft = DeliveryRun::factory()->create(['status' => DeliveryRunStatus::Draft]);
        $keeper = User::factory()->bodeguero()->create();
        Sanctum::actingAs($keeper, $keeper->apiAbilities());

        $this->getJson('/api/v1/sync')
            ->assertOk()
            ->assertJsonPath('resources.dispatch_orders.mode', 'live')
            ->assertJsonPath('resources.delivery_runs.mode', 'live')
            ->assertJsonPath('resources.drivers.mode', 'live')
            ->assertJsonPath('resources.products.mode', 'delta')
            ->assertJsonPath('operations', []);
        $this->getJson('/api/v1/sync/pull/dispatch_orders')
            ->assertOk()
            ->assertJsonPath('records.0.id', $order->id)
            ->assertJsonPath('records.0.items.0.conversion_factor', '1.000000');
        $this->assertContains($draft->id, array_column($this->getJson('/api/v1/sync/pull/delivery_runs')->json('records'), 'id'));
        $this->postJson('/api/v1/sync/push', ['operations' => [
            ['client_reference' => 'B001:1', 'type' => 'delivery.load', 'payload' => ['delivery_run_id' => $draft->id]],
        ]])->assertUnprocessable();

        $driver = User::factory()->repartidor()->create();
        Sanctum::actingAs($driver, $driver->apiAbilities());
        $this->getJson('/api/v1/sync')->assertJsonPath('resources.delivery_runs.mode', 'full');
        $this->assertNotContains($draft->id, array_column($this->getJson('/api/v1/sync/pull/delivery_runs')->json('records'), 'id'));
    }

    public function test_a_run_is_assembled_picked_line_by_line_and_loaded_online(): void
    {
        [, $rice] = $this->product('50');
        [, $beans] = $this->product('50');
        $first = $this->order([[$rice, '6'], [$beans, '2']]);
        $second = $this->order([[$rice, '3']]);
        $keeper = User::factory()->bodeguero()->create();
        $driver = User::factory()->repartidor()->create();
        Sanctum::actingAs($keeper, $keeper->apiAbilities());

        $runId = $this->postJson('/api/v1/delivery-runs', ['warehouse_id' => $first->warehouse_id, 'driver_id' => $driver->id, 'scheduled_date' => today()->toDateString()])
            ->assertCreated()
            ->json('data.id');
        $this->postJson("/api/v1/delivery-runs/{$runId}/orders", ['order_id' => $first->id])->assertCreated();
        $this->postJson("/api/v1/delivery-runs/{$runId}/orders", ['order_id' => $second->id])->assertCreated();
        $this->postJson("/api/v1/delivery-runs/{$runId}/preparation")->assertOk();
        $line = fn (Order $order, int $index): string => $order->items()->orderBy('created_at')->orderBy('id')->get()[$index]->id;

        // By product: the rice of both orders, then the beans of the first.
        $this->putJson("/api/v1/delivery-runs/{$runId}/picks", ['items' => [
            ['order_item_id' => $line($first, 0), 'prepared_quantity' => '6'],
            ['order_item_id' => $line($second, 0), 'prepared_quantity' => '3'],
        ]])->assertOk();
        $run = DeliveryRun::query()->findOrFail($runId);
        $this->assertSame(
            [DeliveryOrderStatus::Pending, DeliveryOrderStatus::Prepared],
            $run->runOrders()->orderBy('visit_order')->get()->pluck('status')->all(),
            'the first order still has its beans to review',
        );
        $this->postJson("/api/v1/delivery-runs/{$runId}/load")->assertUnprocessable();

        $this->putJson("/api/v1/delivery-runs/{$runId}/picks", ['items' => [['order_item_id' => $line($first, 1), 'prepared_quantity' => '2']]])
            ->assertOk()
            ->assertJsonPath('data.orders.0.status', 'prepared');
        $this->postJson("/api/v1/delivery-runs/{$runId}/load")->assertOk();

        $this->assertSame(DeliveryRunStatus::Loaded, $run->refresh()->status);
        $this->assertSame($keeper->id, (int) $run->preparation_started_by);
    }

    public function test_a_pick_over_the_warehouse_stock_is_refused_when_it_is_entered(): void
    {
        $warehouse = Warehouse::query()->where('is_default', true)->sole();
        [$product, $presentation] = $this->product('50');
        $first = $this->order([[$presentation, '6']]);
        $second = $this->order([[$presentation, '6']]);
        $keeper = User::factory()->bodeguero()->create();
        // Damaged after the orders were confirmed: 8 left for 12 ordered.
        app(ApplyInventoryDeltaAction::class)->handle($warehouse, $product, InventoryMovementType::AdjustmentOut, '-42', '0', $keeper);
        Sanctum::actingAs($keeper, $keeper->apiAbilities());
        $runId = $this->assembledRun([$first, $second], $keeper);

        $this->putJson("/api/v1/delivery-runs/{$runId}/picks", ['items' => [['order_item_id' => $first->items()->sole()->id, 'prepared_quantity' => '6']]])->assertOk();
        $this->putJson("/api/v1/delivery-runs/{$runId}/picks", ['items' => [['order_item_id' => $second->items()->sole()->id, 'prepared_quantity' => '6']]])
            ->assertUnprocessable()
            ->assertJsonPath('errors.stock.0', "Solo hay 8 {$product->baseUnit->symbol} de {$product->name} en bodega; con esto la jornada llevaría 12.");
        $this->putJson("/api/v1/delivery-runs/{$runId}/picks", ['items' => [['order_item_id' => $second->items()->sole()->id, 'prepared_quantity' => '2']]])->assertOk();
        $this->postJson("/api/v1/delivery-runs/{$runId}/load")->assertOk();
    }

    public function test_only_the_keeper_who_started_a_preparation_picks_it_and_a_supervisor_may_take_over(): void
    {
        [, $presentation] = $this->product('50');
        $order = $this->order([[$presentation, '6']]);
        $keeper = User::factory()->bodeguero()->create(['name' => 'Rosa Bodega']);
        $other = User::factory()->bodeguero()->create();
        $supervisor = User::factory()->supervisor()->create();
        Sanctum::actingAs($keeper, $keeper->apiAbilities());
        $runId = $this->assembledRun([$order], $keeper);
        $pick = ['items' => [['order_item_id' => $order->items()->sole()->id, 'prepared_quantity' => '6']]];

        Sanctum::actingAs($other, $other->apiAbilities());
        $this->putJson("/api/v1/delivery-runs/{$runId}/picks", $pick)
            ->assertForbidden()
            ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'la está preparando Rosa Bodega'));

        Sanctum::actingAs($supervisor, $supervisor->apiAbilities());
        $this->putJson("/api/v1/delivery-runs/{$runId}/picks", $pick)->assertOk();
    }

    public function test_a_product_count_is_applied_at_the_server_time_and_listed_for_its_keeper(): void
    {
        $warehouse = Warehouse::query()->where('is_default', true)->sole();
        [$product] = $this->product('20');
        $keeper = User::factory()->bodeguero()->create(['name' => 'Rosa Bodega']);
        $supervisor = User::factory()->supervisor()->create();
        Sanctum::actingAs($keeper, $keeper->apiAbilities());

        $this->postJson('/api/v1/inventory/product-counts', ['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'counted_quantity' => '18', 'notes' => 'Dos bolsas rotas'])
            ->assertCreated()
            ->assertJsonPath('data.items.0.expected_quantity', '20.000000')
            ->assertJsonPath('data.items.0.difference', '-2.000000');

        $this->assertSame(InventoryCountStatus::Posted, InventoryCount::query()->sole()->status);
        $this->assertSame('18.000000', InventoryStock::query()->where('product_id', $product->id)->value('quantity_on_hand'));
        $this->assertStringContainsString('Rosa Bodega contó', $supervisor->notifications()->sole()->data['message']);
        $this->getJson('/api/v1/inventory/product-counts')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.items.0.counted_quantity', '18.000000');
        Sanctum::actingAs($other = User::factory()->bodeguero()->create(), $other->apiAbilities());
        $this->getJson('/api/v1/inventory/product-counts')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_a_count_taken_earlier_is_still_compared_with_the_stock_at_that_moment(): void
    {
        $warehouse = Warehouse::query()->where('is_default', true)->sole();
        [$product] = $this->product('20');
        $keeper = User::factory()->bodeguero()->create();
        app(ApplyInventoryDeltaAction::class)->handle($warehouse, $product, InventoryMovementType::AdjustmentOut, '-5', '0', $keeper, ['occurred_at' => now()->subHour()]);
        Sanctum::actingAs($keeper, $keeper->apiAbilities());

        $this->postJson('/api/v1/inventory/product-counts', ['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'counted_quantity' => '18', 'counted_at' => now()->subHours(2)->toIso8601String()])
            ->assertCreated()
            ->assertJsonPath('data.items.0.expected_quantity', '20.000000');
        $this->assertSame('13.000000', InventoryStock::query()->where('product_id', $product->id)->value('quantity_on_hand'));
    }

    public function test_a_receipt_with_its_lines_is_applied_in_one_request(): void
    {
        $warehouse = Warehouse::query()->where('is_default', true)->sole();
        [$product, $presentation] = $this->product('4');
        $keeper = User::factory()->bodeguero()->create();
        Sanctum::actingAs($keeper, $keeper->apiAbilities());

        $this->postJson('/api/v1/inventory/receipts', [
            'warehouse_id' => $warehouse->id,
            'type' => 'receipt',
            'occurred_on' => today()->toDateString(),
            'external_reference' => 'FAC-889',
            'items' => [['product_presentation_id' => $presentation->id, 'quantity' => '10', 'unit_cost' => '12.5']],
        ])->assertCreated();

        $this->assertSame(InventoryDocumentStatus::Posted, InventoryDocument::query()->sole()->status);
        $this->assertSame('14.000000', InventoryStock::query()->where('product_id', $product->id)->value('quantity_on_hand'));
    }

    public function test_a_receipt_asks_for_the_lot_the_product_tracks_and_only_a_supervisor_adjusts(): void
    {
        $warehouse = Warehouse::query()->where('is_default', true)->sole();
        [$product, $presentation] = $this->product('0');
        $product->update(['tracks_lots' => true]);
        $keeper = User::factory()->bodeguero()->create();
        Sanctum::actingAs($keeper, $keeper->apiAbilities());
        $receipt = fn (string $type, ?string $lot): array => [
            'warehouse_id' => $warehouse->id,
            'type' => $type,
            'occurred_on' => today()->toDateString(),
            'items' => [['product_presentation_id' => $presentation->id, 'quantity' => '5', 'lot_number' => $lot]],
        ];

        $this->travel(5)->seconds();
        $this->assertTrue(collect($this->getJson('/api/v1/sync/pull/products')->json('records'))->firstWhere('id', $product->id)['tracks_lots']);
        $this->postJson('/api/v1/inventory/receipts', $receipt('receipt', null))->assertUnprocessable()->assertJsonValidationErrors('lot_number');
        $this->postJson('/api/v1/inventory/receipts', $receipt('adjustment_in', 'L-1'))->assertForbidden();
        $this->postJson('/api/v1/inventory/receipts', $receipt('customer_return', 'L-1'))->assertCreated()->assertJsonPath('data.items.0.lot_number', 'L-1');

        $this->assertSame(1, InventoryDocument::query()->count(), 'a refused receipt leaves nothing behind');
        $this->assertSame('5.000000', InventoryStock::query()->where('product_id', $product->id)->value('quantity_on_hand'));
    }

    /** @param list<Order> $orders */
    private function assembledRun(array $orders, User $keeper): string
    {
        $runId = $this->postJson('/api/v1/delivery-runs', ['warehouse_id' => $orders[0]->warehouse_id, 'driver_id' => User::factory()->repartidor()->create()->id, 'scheduled_date' => today()->toDateString()])->json('data.id');
        foreach ($orders as $order) {
            $this->postJson("/api/v1/delivery-runs/{$runId}/orders", ['order_id' => $order->id])->assertCreated();
        }
        $this->postJson("/api/v1/delivery-runs/{$runId}/preparation")->assertOk();

        return $runId;
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

    /** @param list<array{0: ProductPresentation, 1: string}> $lines */
    private function order(array $lines): Order
    {
        $salesperson = User::factory()->preventista()->create();
        $warehouse = Warehouse::query()->where('is_default', true)->sole();
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
        foreach ($lines as [$presentation, $quantity]) {
            $product = $presentation->product;
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
        }
        app(ConfirmOrderAction::class)->handle($order, $salesperson);

        return $order->refresh();
    }
}
