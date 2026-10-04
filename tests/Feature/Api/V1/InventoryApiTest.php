<?php

namespace Tests\Feature\Api\V1;

use App\InventoryCountStatus;
use App\InventoryDocumentStatus;
use App\InventoryDocumentType;
use App\InventoryMovementType;
use App\Models\InventoryDocument;
use App\Models\InventoryDocumentItem;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\MeasurementUnit;
use App\Models\Product;
use App\Models\ProductPresentation;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventoryApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_inventory_endpoints_require_authentication_token_ability_and_role(): void
    {
        $this->getJson('/api/v1/inventory/stocks')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->admin()->create(), ['catalog:manage']);
        $this->getJson('/api/v1/inventory/stocks')->assertForbidden();

        Sanctum::actingAs(User::factory()->preventista()->create(), ['inventory:view']);
        $this->getJson('/api/v1/warehouses')->assertOk();
        $this->getJson('/api/v1/inventory/stocks')->assertOk();

        Sanctum::actingAs(User::factory()->preventista()->create(), ['inventory:operate']);
        $this->getJson('/api/v1/inventory/documents')->assertForbidden();

        Sanctum::actingAs(User::factory()->repartidor()->create(), ['inventory:view']);
        $this->getJson('/api/v1/inventory/stocks')->assertForbidden();
    }

    public function test_bodeguero_receives_boxes_with_lot_and_expiration_and_server_owns_balances(): void
    {
        $bodeguero = User::factory()->create();
        $warehouse = $this->defaultWarehouse();
        [$product, , $box] = $this->productWithPresentations([
            'tracks_lots' => true,
            'tracks_expiration' => true,
        ]);
        $stock = $this->stock($warehouse, $product);
        Sanctum::actingAs($bodeguero, ['inventory:operate']);

        $response = $this->postJson('/api/v1/inventory/documents', [
            'warehouse_id' => $warehouse->id,
            'type' => InventoryDocumentType::Receipt->value,
            'occurred_on' => now()->toDateString(),
            'external_reference' => 'FAC-001',
            'notes' => 'Recepción verificada',
            'status' => InventoryDocumentStatus::Posted->value,
            'document_number' => 'ALTERADO',
        ]);
        $document = InventoryDocument::query()->sole();

        $response
            ->assertCreated()
            ->assertJsonPath('data.id', $document->id)
            ->assertJsonPath('data.status', InventoryDocumentStatus::Draft->value)
            ->assertJsonPath('data.document_number', $document->document_number);
        $this->assertNotSame('ALTERADO', $document->document_number);

        $expirationDate = now()->addYear()->toDateString();
        $this->postJson("/api/v1/inventory/documents/{$document->id}/items", [
            'product_presentation_id' => $box->id,
            'quantity' => '3',
            'unit_cost' => '205.5000',
            'lot_number' => null,
            'expiration_date' => null,
            'notes' => null,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['lot_number']);
        $this->assertDatabaseCount('inventory_document_items', 0);

        $this->postJson("/api/v1/inventory/documents/{$document->id}/items", [
            'product_presentation_id' => $box->id,
            'quantity' => '3',
            'unit_cost' => '205.5000',
            'lot_number' => 'LOTE-2026-01',
            'expiration_date' => $expirationDate,
            'notes' => null,
            'base_quantity' => '1',
            'product_id' => '01ARBITRARY',
        ])->assertCreated()
            ->assertJsonPath('data.quantity', '3.000000')
            ->assertJsonPath('data.base_quantity', '72.000000')
            ->assertJsonPath('data.lot_number', 'LOTE-2026-01')
            ->assertJsonPath('data.expiration_date', $expirationDate);

        $this->postJson("/api/v1/inventory/documents/{$document->id}/post")
            ->assertOk()
            ->assertJsonPath('data.status', InventoryDocumentStatus::Posted->value);

        $this->assertSame('72.000000', $stock->refresh()->quantity_on_hand);
        $this->assertSame('0.000000', $stock->quantity_reserved);
        $movement = InventoryMovement::query()->sole();
        $this->assertSame(InventoryMovementType::Receipt, $movement->type);
        $this->assertSame('72.000000', $movement->quantity_on_hand_delta);
        $this->assertSame('72.000000', $movement->quantity_on_hand_after);
        $this->assertSame('LOTE-2026-01', $movement->lot_number);
        $this->assertSame($document->document_number, $movement->reference_number);

        $this->putJson("/api/v1/inventory/documents/{$document->id}", [
            'warehouse_id' => $warehouse->id,
            'type' => InventoryDocumentType::Receipt->value,
            'occurred_on' => now()->toDateString(),
        ])->assertForbidden();
        $this->postJson("/api/v1/inventory/documents/{$document->id}/post")->assertForbidden();
        $this->assertDatabaseCount('inventory_movements', 1);

        $otherUnit = MeasurementUnit::factory()->create();
        Sanctum::actingAs(User::factory()->admin()->create(), ['catalog:manage']);
        $this->putJson("/api/v1/products/{$product->id}", [
            'category_id' => $product->category_id,
            'brand_id' => $product->brand_id,
            'base_unit_id' => $otherUnit->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'description' => $product->description,
            'allows_decimal' => $product->allows_decimal,
            'tracks_lots' => $product->tracks_lots,
            'tracks_expiration' => $product->tracks_expiration,
            'is_active' => true,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('base_unit_id');
        $this->assertSame($product->base_unit_id, $product->refresh()->base_unit_id);

        $this->deleteJson("/api/v1/products/{$product->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product');
        $this->assertNull($product->refresh()->deleted_at);
    }

    public function test_manual_adjustment_requires_supervised_role_and_explicit_token_ability(): void
    {
        $warehouse = $this->defaultWarehouse();
        $payload = [
            'warehouse_id' => $warehouse->id,
            'type' => InventoryDocumentType::AdjustmentIn->value,
            'occurred_on' => now()->toDateString(),
            'notes' => 'Corrección autorizada',
        ];
        $supervisor = User::factory()->supervisor()->create();
        [, $presentation] = $this->productWithPresentations();

        Sanctum::actingAs($supervisor, ['inventory:operate']);
        $this->postJson('/api/v1/inventory/documents', $payload)->assertForbidden();

        Sanctum::actingAs($supervisor, ['inventory:operate', 'inventory:adjust']);
        $documentResponse = $this->postJson('/api/v1/inventory/documents', $payload)
            ->assertCreated()
            ->assertJsonPath('data.type', InventoryDocumentType::AdjustmentIn->value);
        $documentId = $documentResponse->json('data.id');

        Sanctum::actingAs($supervisor, ['inventory:operate']);
        $this->postJson("/api/v1/inventory/documents/{$documentId}/items", [
            'product_presentation_id' => $presentation->id,
            'quantity' => '1',
            'unit_cost' => null,
            'lot_number' => null,
            'expiration_date' => null,
            'notes' => null,
        ])->assertForbidden();

        Sanctum::actingAs(User::factory()->create(), ['inventory:operate', 'inventory:adjust']);
        $this->postJson('/api/v1/inventory/documents', $payload)->assertForbidden();
        $this->assertDatabaseCount('inventory_documents', 1);
    }

    public function test_configuration_creates_stock_for_new_warehouse_and_cannot_overwrite_quantities(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        [$product] = $this->productWithPresentations();
        $defaultWarehouse = $this->defaultWarehouse();
        $defaultStock = $this->stock($defaultWarehouse, $product, '8', '2');

        Sanctum::actingAs($supervisor, ['inventory:view']);
        $this->putJson("/api/v1/inventory/stocks/{$defaultStock->id}", [
            'reorder_point' => '4',
        ])->assertForbidden();

        Sanctum::actingAs($supervisor, ['inventory:configure']);
        $this->postJson('/api/v1/warehouses', [
            'code' => 'bod-norte',
            'name' => 'Bodega Norte',
            'address' => 'Salida norte',
            'is_default' => false,
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('data.code', 'BOD-NORTE')
            ->assertJsonPath('data.is_default', false);

        $newWarehouse = Warehouse::query()->where('code', 'BOD-NORTE')->sole();
        $this->assertDatabaseHas('inventory_stocks', [
            'warehouse_id' => $newWarehouse->id,
            'product_id' => $product->id,
            'quantity_on_hand' => 0,
            'quantity_reserved' => 0,
        ]);

        $inactiveWarehouse = Warehouse::factory()->for($supervisor, 'creator')->create([
            'code' => 'BOD-INACTIVA',
            'name' => 'Bodega inactiva',
            'is_default' => false,
            'is_active' => false,
        ]);
        $this->assertDatabaseMissing('inventory_stocks', [
            'warehouse_id' => $inactiveWarehouse->id,
            'product_id' => $product->id,
        ]);
        $this->putJson("/api/v1/warehouses/{$inactiveWarehouse->id}", [
            'code' => $inactiveWarehouse->code,
            'name' => $inactiveWarehouse->name,
            'address' => null,
            'is_default' => false,
            'is_active' => true,
        ])->assertOk()
            ->assertJsonPath('data.is_active', true);
        $this->assertDatabaseHas('inventory_stocks', [
            'warehouse_id' => $inactiveWarehouse->id,
            'product_id' => $product->id,
            'quantity_on_hand' => 0,
            'quantity_reserved' => 0,
        ]);

        $this->putJson("/api/v1/inventory/stocks/{$defaultStock->id}", [
            'reorder_point' => '4',
            'quantity_on_hand' => '999',
            'quantity_reserved' => '999',
        ])->assertOk()
            ->assertJsonPath('data.reorder_point', '4.000000')
            ->assertJsonPath('data.quantity_on_hand', '8.000000')
            ->assertJsonPath('data.quantity_reserved', '2.000000');
    }

    public function test_physical_count_api_is_blind_until_posting_and_records_difference(): void
    {
        $bodeguero = User::factory()->create();
        $warehouse = $this->defaultWarehouse();
        [$product] = $this->productWithPresentations();
        $stock = $this->stock($warehouse, $product, '10', '3');
        Sanctum::actingAs($bodeguero, ['inventory:operate']);

        $createResponse = $this->postJson('/api/v1/inventory/counts', [
            'warehouse_id' => $warehouse->id,
            'counted_on' => now()->toDateString(),
            'notes' => 'Conteo mensual',
        ])->assertCreated()
            ->assertJsonPath('data.status', InventoryCountStatus::Draft->value)
            ->assertJsonCount(1, 'data.items');
        $this->assertArrayNotHasKey('expected_quantity', $createResponse->json('data.items.0'));
        $this->assertArrayNotHasKey('difference', $createResponse->json('data.items.0'));

        $countId = $createResponse->json('data.id');
        $itemId = $createResponse->json('data.items.0.id');
        $updateResponse = $this->putJson("/api/v1/inventory/counts/{$countId}", [
            'items' => [[
                'id' => $itemId,
                'counted_quantity' => '7',
                'notes' => 'Tres unidades faltantes',
            ]],
        ])->assertOk()
            ->assertJsonPath('data.items.0.counted_quantity', '7.000000');
        $this->assertArrayNotHasKey('expected_quantity', $updateResponse->json('data.items.0'));

        $this->postJson("/api/v1/inventory/counts/{$countId}/post")
            ->assertOk()
            ->assertJsonPath('data.status', InventoryCountStatus::Posted->value)
            ->assertJsonPath('data.items.0.expected_quantity', '10.000000')
            ->assertJsonPath('data.items.0.difference', '-3.000000');

        $this->assertSame('7.000000', $stock->refresh()->quantity_on_hand);
        $this->assertSame('3.000000', $stock->quantity_reserved);
        $this->assertDatabaseHas('inventory_movements', [
            'inventory_count_id' => $countId,
            'type' => InventoryMovementType::PhysicalCountOut->value,
            'quantity_on_hand_delta' => -3,
            'quantity_reserved_delta' => 0,
        ]);
    }

    public function test_nested_document_items_are_scoped_to_their_parent(): void
    {
        $bodeguero = User::factory()->create();
        $warehouse = $this->defaultWarehouse();
        [, $presentation] = $this->productWithPresentations();
        $firstDocument = InventoryDocument::factory()
            ->for($warehouse)
            ->for($bodeguero, 'creator')
            ->create();
        $secondDocument = InventoryDocument::factory()
            ->for($warehouse)
            ->for($bodeguero, 'creator')
            ->create();
        $foreignItem = InventoryDocumentItem::factory()
            ->for($secondDocument)
            ->create([
                'product_id' => $presentation->product_id,
                'product_presentation_id' => $presentation->id,
            ]);
        Sanctum::actingAs($bodeguero, ['inventory:operate']);

        $this->putJson("/api/v1/inventory/documents/{$firstDocument->id}/items/{$foreignItem->id}", [
            'product_presentation_id' => $presentation->id,
            'quantity' => '2',
            'unit_cost' => null,
            'lot_number' => null,
            'expiration_date' => null,
            'notes' => null,
        ])->assertNotFound();

        $this->assertSame('1.000000', $foreignItem->refresh()->quantity);
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
        string $onHand = '0',
        string $reserved = '0',
    ): InventoryStock {
        return InventoryStock::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity_on_hand' => $onHand,
            'quantity_reserved' => $reserved,
            'reorder_point' => '0',
        ]);
    }
}
