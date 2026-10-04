<?php

namespace App\Actions;

use App\InventoryMovementType;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ApplyInventoryDeltaAction
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function handle(
        Warehouse $warehouse,
        Product $product,
        InventoryMovementType $type,
        string $onHandDelta,
        string $reservedDelta,
        User $actor,
        array $context = [],
    ): InventoryMovement {
        return DB::transaction(function () use (
            $warehouse,
            $product,
            $type,
            $onHandDelta,
            $reservedDelta,
            $actor,
            $context,
        ): InventoryMovement {
            if (bccomp($onHandDelta, '0', 6) === 0 && bccomp($reservedDelta, '0', 6) === 0) {
                throw ValidationException::withMessages([
                    'quantity' => ['El movimiento no puede tener ambas cantidades en cero.'],
                ]);
            }

            DB::table('inventory_stocks')->insertOrIgnore([
                'id' => (string) Str::ulid(),
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'quantity_on_hand' => '0.000000',
                'quantity_reserved' => '0.000000',
                'reorder_point' => '0.000000',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $stock = InventoryStock::query()
                ->where('warehouse_id', $warehouse->id)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->firstOrFail();
            $onHandAfter = bcadd((string) $stock->quantity_on_hand, $onHandDelta, 6);
            $reservedAfter = bcadd((string) $stock->quantity_reserved, $reservedDelta, 6);

            if (bccomp($onHandAfter, '0', 6) < 0) {
                throw ValidationException::withMessages([
                    'quantity' => ["No hay existencia física suficiente de {$product->name} para aplicar la salida."],
                ]);
            }

            if (bccomp($reservedAfter, '0', 6) < 0) {
                throw ValidationException::withMessages([
                    'quantity_reserved' => ["La liberación supera la cantidad reservada de {$product->name}."],
                ]);
            }

            $stock->forceFill([
                'quantity_on_hand' => $onHandAfter,
                'quantity_reserved' => $reservedAfter,
            ])->save();

            $snapshotProduct = Product::withTrashed()->with('baseUnit')->findOrFail($product->id);

            return InventoryMovement::query()->create([
                'warehouse_id' => $warehouse->id,
                'product_id' => $snapshotProduct->id,
                'product_presentation_id' => $context['product_presentation_id'] ?? null,
                'inventory_document_id' => $context['inventory_document_id'] ?? null,
                'inventory_count_id' => $context['inventory_count_id'] ?? null,
                'order_id' => $context['order_id'] ?? null,
                'order_item_id' => $context['order_item_id'] ?? null,
                'type' => $type,
                'occurred_at' => $context['occurred_at'] ?? now(),
                'quantity_on_hand_delta' => $onHandDelta,
                'quantity_reserved_delta' => $reservedDelta,
                'quantity_on_hand_after' => $onHandAfter,
                'quantity_reserved_after' => $reservedAfter,
                'presentation_quantity' => $context['presentation_quantity'] ?? null,
                'conversion_factor' => $context['conversion_factor'] ?? null,
                'product_sku' => $context['product_sku'] ?? $snapshotProduct->sku,
                'product_name' => $context['product_name'] ?? $snapshotProduct->name,
                'presentation_name' => $context['presentation_name'] ?? null,
                'base_unit_symbol' => $context['base_unit_symbol'] ?? $snapshotProduct->baseUnit->symbol,
                'reference_number' => $context['reference_number'] ?? null,
                'lot_number' => $context['lot_number'] ?? null,
                'expiration_date' => $context['expiration_date'] ?? null,
                'reason' => $context['reason'] ?? null,
                'created_by' => $actor->id,
            ]);
        }, 3);
    }
}
