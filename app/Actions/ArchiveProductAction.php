<?php

namespace App\Actions;

use App\InventoryCountStatus;
use App\InventoryDocumentStatus;
use App\Models\InventoryCountItem;
use App\Models\InventoryDocumentItem;
use App\Models\InventoryStock;
use App\Models\OrderItem;
use App\Models\Product;
use App\OrderStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ArchiveProductAction
{
    public function handle(Product $product): void
    {
        DB::transaction(function () use ($product): void {
            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->id);
            $stocks = InventoryStock::query()
                ->where('product_id', $lockedProduct->id)
                ->lockForUpdate()
                ->get();
            $hasStock = $stocks->contains(fn (InventoryStock $stock): bool => bccomp((string) $stock->quantity_on_hand, '0', 6) !== 0
                || bccomp((string) $stock->quantity_reserved, '0', 6) !== 0);

            if ($hasStock) {
                throw ValidationException::withMessages([
                    'product' => ['No puedes archivar un producto con existencia física o reservas pendientes. Deja ambas cantidades en cero primero.'],
                ]);
            }

            $hasOpenOperation = OrderItem::query()
                ->where('product_id', $lockedProduct->id)
                ->whereHas('order', fn ($query) => $query->where('status', OrderStatus::Draft->value))
                ->exists()
                || InventoryDocumentItem::query()
                    ->where('product_id', $lockedProduct->id)
                    ->whereHas('inventoryDocument', fn ($query) => $query->where('status', InventoryDocumentStatus::Draft->value))
                    ->exists()
                || InventoryCountItem::query()
                    ->where('product_id', $lockedProduct->id)
                    ->whereHas('inventoryCount', fn ($query) => $query->where('status', InventoryCountStatus::Draft->value))
                    ->exists();

            if ($hasOpenOperation) {
                throw ValidationException::withMessages([
                    'product' => ['No puedes archivar el producto mientras figure en un pedido, movimiento o conteo abierto. Cierra o cancela esas operaciones primero.'],
                ]);
            }

            $lockedProduct->forceFill(['is_active' => false])->save();
            $lockedProduct->delete();
        }, 3);
    }
}
