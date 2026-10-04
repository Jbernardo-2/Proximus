<?php

namespace App\Actions;

use App\Models\InventoryCount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveInventoryCountItemsAction
{
    /** @param list<array{id: string, counted_quantity: string|null, notes?: string|null}> $items */
    public function handle(InventoryCount $count, array $items): InventoryCount
    {
        return DB::transaction(function () use ($count, $items): InventoryCount {
            $lockedCount = InventoryCount::query()->lockForUpdate()->findOrFail($count->id);

            if (! $lockedCount->isDraft()) {
                throw ValidationException::withMessages([
                    'count' => ['Solo se puede editar un conteo en proceso.'],
                ]);
            }

            foreach ($items as $data) {
                $item = $lockedCount->items()->with('product')->lockForUpdate()->findOrFail($data['id']);
                $countedQuantity = $data['counted_quantity'];

                if ($countedQuantity !== null
                    && ! $item->product->allows_decimal
                    && bccomp($countedQuantity, bcadd($countedQuantity, '0', 0), 6) !== 0) {
                    throw ValidationException::withMessages([
                        'items' => ["{$item->product_name} solo admite cantidades enteras."],
                    ]);
                }
                $item->forceFill([
                    'counted_quantity' => $countedQuantity,
                    'difference' => $countedQuantity === null
                        ? null
                        : bcsub($countedQuantity, (string) $item->expected_quantity, 6),
                    'notes' => $data['notes'] ?? null,
                ])->save();
            }

            return $lockedCount->refresh();
        });
    }
}
