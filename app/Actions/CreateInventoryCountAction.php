<?php

namespace App\Actions;

use App\InventoryCountStatus;
use App\Models\InventoryCount;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateInventoryCountAction
{
    public function __construct(private GenerateInventoryNumberAction $generateNumber) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data, User $actor): InventoryCount
    {
        return DB::transaction(function () use ($data, $actor): InventoryCount {
            $warehouse = Warehouse::query()->lockForUpdate()->findOrFail($data['warehouse_id']);

            if (! $warehouse->is_active) {
                throw ValidationException::withMessages([
                    'warehouse_id' => ['La bodega seleccionada no está activa.'],
                ]);
            }

            if (InventoryCount::query()
                ->where('warehouse_id', $warehouse->id)
                ->where('status', InventoryCountStatus::Draft->value)
                ->exists()) {
                throw ValidationException::withMessages([
                    'warehouse_id' => ['Ya existe un conteo físico en proceso para esta bodega.'],
                ]);
            }

            $products = Product::query()
                ->withTrashed()
                ->where(function ($query) use ($warehouse): void {
                    $query->where('is_active', true)
                        ->orWhereHas('inventoryStocks', function ($stocks) use ($warehouse): void {
                            $stocks->where('warehouse_id', $warehouse->id)
                                ->where(function ($quantities): void {
                                    $quantities->where('quantity_on_hand', '!=', 0)
                                        ->orWhere('quantity_reserved', '!=', 0);
                                });
                        });
                })
                ->with('baseUnit')
                ->orderBy('name')
                ->get();

            if ($products->isEmpty()) {
                throw ValidationException::withMessages([
                    'products' => ['No hay productos activos para contar.'],
                ]);
            }

            $countedOn = CarbonImmutable::parse($data['counted_on'])->startOfDay();
            $count = InventoryCount::query()->create([
                'count_number' => $this->generateNumber->handle('physical-count', 'CON', $countedOn),
                'warehouse_id' => $warehouse->id,
                'status' => InventoryCountStatus::Draft,
                'counted_on' => $countedOn,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);
            $stocks = InventoryStock::query()
                ->where('warehouse_id', $warehouse->id)
                ->whereIn('product_id', $products->pluck('id'))
                ->pluck('quantity_on_hand', 'product_id');

            foreach ($products as $product) {
                $count->items()->create([
                    'product_id' => $product->id,
                    'product_sku' => $product->sku,
                    'product_name' => $product->name,
                    'base_unit_symbol' => $product->baseUnit->symbol,
                    'expected_quantity' => (string) ($stocks[$product->id] ?? '0.000000'),
                    'counted_quantity' => null,
                    'difference' => null,
                ]);
            }

            return $count;
        });
    }
}
