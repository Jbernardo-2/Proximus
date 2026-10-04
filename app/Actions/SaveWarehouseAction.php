<?php

namespace App\Actions;

use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveWarehouseAction
{
    /** @param array<string, mixed> $data */
    public function handle(array $data, User $actor, ?Warehouse $warehouse = null): Warehouse
    {
        return DB::transaction(function () use ($data, $actor, $warehouse): Warehouse {
            Warehouse::query()->orderBy('id')->lockForUpdate()->get();
            $lockedWarehouse = $warehouse === null
                ? null
                : Warehouse::query()->findOrFail($warehouse->id);
            $isDefault = (bool) $data['is_default'];
            $isActive = (bool) $data['is_active'];

            if ($isDefault && ! $isActive) {
                throw ValidationException::withMessages([
                    'is_active' => ['La bodega predeterminada debe permanecer activa.'],
                ]);
            }

            if ($lockedWarehouse?->is_default && ! $isDefault) {
                $hasOtherDefault = Warehouse::query()
                    ->where('id', '!=', $lockedWarehouse->id)
                    ->where('is_default', true)
                    ->where('is_active', true)
                    ->exists();

                if (! $hasOtherDefault) {
                    throw ValidationException::withMessages([
                        'is_default' => ['Selecciona primero otra bodega predeterminada.'],
                    ]);
                }
            }

            if ($lockedWarehouse !== null && ! $isActive) {
                $otherActive = Warehouse::query()
                    ->where('id', '!=', $lockedWarehouse->id)
                    ->where('is_active', true)
                    ->exists();

                if (! $otherActive) {
                    throw ValidationException::withMessages([
                        'is_active' => ['Debe existir al menos una bodega activa.'],
                    ]);
                }
            }

            if ($isDefault) {
                Warehouse::query()->where('is_default', true)->update(['is_default' => false]);
            }

            $attributes = [
                'code' => $data['code'],
                'name' => $data['name'],
                'address' => $data['address'] ?? null,
                'is_default' => $isDefault,
                'is_active' => $isActive,
            ];

            if ($lockedWarehouse === null) {
                $lockedWarehouse = Warehouse::query()->create([
                    ...$attributes,
                    'created_by' => $actor->id,
                ]);
            } else {
                $lockedWarehouse->update($attributes);
            }

            if ($isActive) {
                foreach (Product::query()->pluck('id') as $productId) {
                    $lockedWarehouse->stocks()->firstOrCreate(
                        ['product_id' => $productId],
                        [
                            'quantity_on_hand' => '0.000000',
                            'quantity_reserved' => '0.000000',
                            'reorder_point' => '0.000000',
                        ],
                    );
                }
            }

            return $lockedWarehouse->refresh();
        });
    }
}
