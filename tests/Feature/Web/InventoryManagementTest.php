<?php

namespace Tests\Feature\Web;

use App\InventoryCountStatus;
use App\InventoryDocumentStatus;
use App\InventoryDocumentType;
use App\InventoryMovementType;
use App\InventoryReservationStatus;
use App\Models\InventoryCount;
use App\Models\InventoryDocument;
use App\Models\InventoryReservation;
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
use Tests\TestCase;

class InventoryManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_inventory_pages_follow_role_boundaries(): void
    {
        $preventista = User::factory()->preventista()->create();
        $bodeguero = User::factory()->create();
        $repartidor = User::factory()->repartidor()->create();

        $this->actingAs($preventista)->get(route('inventory.index'))->assertOk();
        $this->actingAs($preventista)->get(route('inventory-documents.index'))->assertForbidden();
        $this->actingAs($bodeguero)->get(route('inventory-documents.index'))->assertOk();
        $this->actingAs($bodeguero)->get(route('warehouses.index'))->assertForbidden();
        $this->actingAs($repartidor)->get(route('inventory.index'))->assertForbidden();
    }

    public function test_receipt_converts_boxes_tracks_lot_and_cannot_be_rewritten_after_posting(): void
    {
        $bodeguero = User::factory()->create();
        $warehouse = $this->defaultWarehouse();
        [$product, , $box] = $this->productWithPresentations([
            'tracks_lots' => true,
            'tracks_expiration' => true,
        ]);

        $this->actingAs($bodeguero)->post(route('inventory-documents.store'), [
            'warehouse_id' => $warehouse->id,
            'type' => InventoryDocumentType::Receipt->value,
            'occurred_on' => now()->toDateString(),
            'supplier_id' => null,
            'external_reference' => 'FAC-7788',
            'notes' => 'Primera recepción',
            'status' => InventoryDocumentStatus::Posted->value,
            'posted_by' => 999,
        ])->assertRedirect();

        $document = InventoryDocument::query()->sole();
        $this->assertSame(InventoryDocumentStatus::Draft, $document->status);
        $this->assertNull($document->posted_by);

        $this->actingAs($bodeguero)->post(route('inventory-documents.items.store', $document), [
            'product_presentation_id' => $box->id,
            'quantity' => '3',
            'unit_cost' => '190.0000',
            'lot_number' => 'LOT-2026-A',
            'expiration_date' => now()->addYear()->toDateString(),
            'notes' => null,
            'base_quantity' => '1',
        ])->assertRedirect(route('inventory-documents.show', $document));

        $item = $document->items()->sole();
        $this->assertSame('72.000000', $item->base_quantity);
        $this->actingAs($bodeguero)->post(route('inventory-documents.post', $document))->assertRedirect();

        $stock = InventoryStock::query()->where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->sole();
        $this->assertSame('72.000000', $stock->quantity_on_hand);
        $this->assertSame('0.000000', $stock->quantity_reserved);
        $this->assertSame(InventoryDocumentStatus::Posted, $document->refresh()->status);
        $this->assertDatabaseHas('inventory_movements', [
            'inventory_document_id' => $document->id,
            'type' => InventoryMovementType::Receipt->value,
            'quantity_on_hand_delta' => 72,
            'lot_number' => 'LOT-2026-A',
            'reference_number' => $document->document_number,
        ]);

        $this->actingAs($bodeguero)->put(route('inventory-documents.items.update', [$document, $item]), [
            'product_presentation_id' => $box->id,
            'quantity' => '99',
            'lot_number' => 'LOT-2026-A',
            'expiration_date' => now()->addYear()->toDateString(),
        ])->assertForbidden();
        $this->assertSame('3.000000', $item->refresh()->quantity);
    }

    public function test_outbound_adjustment_is_supervised_and_rolls_back_every_line_when_stock_is_insufficient(): void
    {
        $bodeguero = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $warehouse = $this->defaultWarehouse();
        [$firstProduct, $firstUnit] = $this->productWithPresentations();
        [$secondProduct, $secondUnit] = $this->productWithPresentations();
        $this->stock($warehouse, $firstProduct, '10');
        $this->stock($warehouse, $secondProduct, '1');

        $payload = [
            'warehouse_id' => $warehouse->id,
            'type' => InventoryDocumentType::AdjustmentOut->value,
            'occurred_on' => now()->toDateString(),
            'supplier_id' => null,
            'external_reference' => null,
            'notes' => 'Daño comprobado por supervisión',
        ];
        $this->actingAs($bodeguero)->post(route('inventory-documents.store'), $payload)->assertForbidden();
        $this->actingAs($admin)->post(route('inventory-documents.store'), $payload)->assertRedirect();
        $document = InventoryDocument::query()->sole();

        $this->actingAs($bodeguero)->get(route('inventory-documents.show', $document))
            ->assertOk()
            ->assertDontSee('Agregar producto')
            ->assertDontSee('Aplicar movimiento');
        $this->actingAs($bodeguero)->post(route('inventory-documents.items.store', $document), [
            'product_presentation_id' => $firstUnit->id,
            'quantity' => '1',
            'unit_cost' => null,
            'lot_number' => null,
            'expiration_date' => null,
            'notes' => null,
        ])->assertForbidden();

        foreach ([[$firstUnit, '2'], [$secondUnit, '3']] as [$presentation, $quantity]) {
            $this->actingAs($admin)->post(route('inventory-documents.items.store', $document), [
                'product_presentation_id' => $presentation->id,
                'quantity' => $quantity,
                'unit_cost' => null,
                'lot_number' => null,
                'expiration_date' => null,
                'notes' => null,
            ])->assertRedirect();
        }

        $firstItem = $document->items()->oldest('id')->firstOrFail();
        $this->actingAs($bodeguero)
            ->delete(route('inventory-documents.items.destroy', [$document, $firstItem]))
            ->assertForbidden();
        $this->assertDatabaseCount('inventory_document_items', 2);

        $this->actingAs($admin)->post(route('inventory-documents.post', $document))
            ->assertSessionHasErrors('quantity');

        $this->assertSame('10.000000', $this->stock($warehouse, $firstProduct)->quantity_on_hand);
        $this->assertSame('1.000000', $this->stock($warehouse, $secondProduct)->quantity_on_hand);
        $this->assertSame(InventoryDocumentStatus::Draft, $document->refresh()->status);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_confirming_order_reserves_full_demand_and_cancelling_releases_it_even_when_stock_is_short(): void
    {
        $preventista = User::factory()->preventista()->create();
        $supervisor = User::factory()->supervisor()->create();
        $warehouse = $this->defaultWarehouse();
        [$product, $unit] = $this->productWithPresentations();
        $stock = $this->stock($warehouse, $product, '10');
        $order = Order::factory()->create([
            'warehouse_id' => $warehouse->id,
            'warehouse_code' => $warehouse->code,
            'warehouse_name' => $warehouse->name,
            'salesperson_id' => $preventista->id,
            'created_by' => $preventista->id,
        ]);
        $this->createOrderItem($order, $product, $unit, '18');

        $this->actingAs($preventista)->post(route('orders.confirm', $order))->assertRedirect();

        $reservation = InventoryReservation::query()->sole();
        $this->assertSame(InventoryReservationStatus::Active, $reservation->status);
        $this->assertSame('18.000000', $reservation->base_quantity);
        $this->assertSame('18.000000', $stock->refresh()->quantity_reserved);
        $this->assertSame('-8.000000', $stock->availableQuantity());
        $this->assertSame(OrderStatus::Confirmed, $order->refresh()->status);

        $this->actingAs($supervisor)->post(route('orders.cancel', $order), [
            'reason' => 'Cliente canceló el pedido.',
        ])->assertRedirect();

        $this->assertSame('0.000000', $stock->refresh()->quantity_reserved);
        $this->assertSame(InventoryReservationStatus::Released, $reservation->refresh()->status);
        $this->assertDatabaseHas('inventory_movements', [
            'order_id' => $order->id,
            'type' => InventoryMovementType::OrderReservationRelease->value,
            'quantity_reserved_delta' => -18,
        ]);
    }

    public function test_reopening_and_reconfirming_an_order_never_duplicates_its_reservation(): void
    {
        $preventista = User::factory()->preventista()->create();
        $supervisor = User::factory()->supervisor()->create();
        $warehouse = $this->defaultWarehouse();
        [$product, $unit] = $this->productWithPresentations();
        $stock = $this->stock($warehouse, $product, '20');
        $order = Order::factory()->create([
            'warehouse_id' => $warehouse->id,
            'warehouse_code' => $warehouse->code,
            'warehouse_name' => $warehouse->name,
            'salesperson_id' => $preventista->id,
            'created_by' => $preventista->id,
        ]);
        $this->createOrderItem($order, $product, $unit, '4');

        $this->actingAs($preventista)->post(route('orders.confirm', $order))->assertRedirect();
        $reservation = InventoryReservation::query()->sole();
        $this->assertSame('4.000000', $stock->refresh()->quantity_reserved);

        $this->actingAs($supervisor)->post(route('orders.reopen', $order), [
            'reason' => 'Cliente solicitó corregir cantidades.',
        ])->assertRedirect();
        $this->assertSame(OrderStatus::Draft, $order->refresh()->status);
        $this->assertSame(InventoryReservationStatus::Released, $reservation->refresh()->status);
        $this->assertSame('0.000000', $stock->refresh()->quantity_reserved);

        $this->actingAs($preventista)->post(route('orders.confirm', $order))->assertRedirect();
        $this->assertSame(InventoryReservationStatus::Active, $reservation->refresh()->status);
        $this->assertSame('4.000000', $stock->refresh()->quantity_reserved);
        $this->assertDatabaseCount('inventory_reservations', 1);
        $this->assertDatabaseCount('inventory_movements', 3);
    }

    public function test_physical_count_is_blind_preserves_reservations_and_posts_audited_difference(): void
    {
        $bodeguero = User::factory()->create();
        $warehouse = $this->defaultWarehouse();
        [$product] = $this->productWithPresentations();
        $stock = $this->stock($warehouse, $product, '10', '4');

        $this->actingAs($bodeguero)->post(route('inventory-counts.store'), [
            'warehouse_id' => $warehouse->id,
            'counted_on' => now()->toDateString(),
            'notes' => 'Conteo de cierre',
        ])->assertRedirect();
        $count = InventoryCount::query()->sole();
        $item = $count->items()->where('product_id', $product->id)->sole();

        $this->actingAs($bodeguero)->get(route('inventory-counts.show', $count))
            ->assertOk()
            ->assertSee('Conteo ciego')
            ->assertDontSee('Sistema</th>', false);
        $this->actingAs($bodeguero)->put(route('inventory-counts.update', $count), [
            'items' => [[
                'id' => $item->id,
                'counted_quantity' => '8',
                'notes' => 'Dos unidades dañadas',
            ]],
        ])->assertRedirect();
        $this->actingAs($bodeguero)->post(route('inventory-counts.post', $count))->assertRedirect();

        $this->assertSame(InventoryCountStatus::Posted, $count->refresh()->status);
        $this->assertSame('8.000000', $stock->refresh()->quantity_on_hand);
        $this->assertSame('4.000000', $stock->quantity_reserved);
        $this->assertSame('-2.000000', $item->refresh()->difference);
        $this->assertDatabaseHas('inventory_movements', [
            'inventory_count_id' => $count->id,
            'type' => InventoryMovementType::PhysicalCountOut->value,
            'quantity_on_hand_delta' => -2,
            'reason' => 'Dos unidades dañadas',
        ]);
    }

    public function test_count_refuses_to_overwrite_stock_that_changed_after_snapshot(): void
    {
        $bodeguero = User::factory()->create();
        $warehouse = $this->defaultWarehouse();
        [$product] = $this->productWithPresentations();
        $stock = $this->stock($warehouse, $product, '5');

        $this->actingAs($bodeguero)->post(route('inventory-counts.store'), [
            'warehouse_id' => $warehouse->id,
            'counted_on' => now()->toDateString(),
            'notes' => null,
        ]);
        $count = InventoryCount::query()->sole();
        $item = $count->items()->where('product_id', $product->id)->sole();
        $this->actingAs($bodeguero)->put(route('inventory-counts.update', $count), [
            'items' => [['id' => $item->id, 'counted_quantity' => '5', 'notes' => null]],
        ]);
        $stock->update(['quantity_on_hand' => '6.000000']);

        $this->actingAs($bodeguero)->post(route('inventory-counts.post', $count))
            ->assertSessionHasErrors('count');

        $this->assertSame(InventoryCountStatus::Draft, $count->refresh()->status);
        $this->assertSame('6.000000', $stock->refresh()->quantity_on_hand);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    private function defaultWarehouse(): Warehouse
    {
        return Warehouse::query()->where('is_default', true)->sole();
    }

    /** @return array{Product, ProductPresentation, ProductPresentation} */
    private function productWithPresentations(array $attributes = []): array
    {
        $unit = MeasurementUnit::factory()->create([
            'name' => fake()->unique()->bothify('Unidad-####-????'),
            'symbol' => fake()->unique()->lexify('u?'),
        ]);
        $product = Product::factory()->for($unit, 'baseUnit')->create($attributes);
        $base = ProductPresentation::factory()->for($product)->base()->create([
            'name' => 'Unidad',
            'conversion_factor' => '1.000000',
        ]);
        $box = ProductPresentation::factory()->for($product)->create([
            'name' => 'Caja 24',
            'conversion_factor' => '24.000000',
        ]);

        return [$product, $base, $box];
    }

    private function stock(
        Warehouse $warehouse,
        Product $product,
        ?string $onHand = null,
        string $reserved = '0',
    ): InventoryStock {
        $stock = InventoryStock::query()->firstOrCreate(
            ['warehouse_id' => $warehouse->id, 'product_id' => $product->id],
            ['quantity_on_hand' => '0', 'quantity_reserved' => '0', 'reorder_point' => '0'],
        );

        if ($onHand !== null) {
            $stock->update(['quantity_on_hand' => $onHand, 'quantity_reserved' => $reserved]);
        }

        return $stock->refresh();
    }

    private function createOrderItem(
        Order $order,
        Product $product,
        ProductPresentation $presentation,
        string $quantity,
    ): OrderItem {
        return OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_presentation_id' => $presentation->id,
            'price_tier_id' => null,
            'product_sku' => $product->sku,
            'product_name' => $product->name,
            'presentation_name' => $presentation->name,
            'base_unit_symbol' => $product->baseUnit->symbol,
            'conversion_factor' => $presentation->conversion_factor,
            'quantity' => $quantity,
            'base_quantity' => bcmul($quantity, (string) $presentation->conversion_factor, 6),
            'standard_unit_price' => '10.0000',
            'unit_price' => '10.0000',
            'price_source' => OrderPriceSource::Presentation,
            'price_overridden_by' => null,
            'override_reason' => null,
            'line_total' => bcmul($quantity, '10.0000', 4),
            'notes' => null,
        ]);
    }
}
