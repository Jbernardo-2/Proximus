<?php

namespace App\Actions;

use App\InventoryCountStatus;
use App\InventoryMovementType;
use App\Models\InventoryCount;
use App\Models\InventoryStock;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PostInventoryCountAction
{
    public function __construct(private ApplyInventoryDeltaAction $applyDelta) {}

    public function handle(InventoryCount $count, User $actor): InventoryCount
    {
        return DB::transaction(function () use ($count, $actor): InventoryCount {
            $lockedCount = InventoryCount::query()->lockForUpdate()->findOrFail($count->id);

            if (! $lockedCount->isDraft()) {
                throw ValidationException::withMessages([
                    'count' => ['Solo se puede aplicar un conteo en proceso.'],
                ]);
            }

            $lockedCount->load(['warehouse', 'items.product']);
            $missing = $lockedCount->items->whereNull('counted_quantity')->count();

            if ($missing > 0) {
                throw ValidationException::withMessages([
                    'items' => ["Faltan {$missing} productos por contar."],
                ]);
            }

            foreach ($lockedCount->items as $item) {
                DB::table('inventory_stocks')->insertOrIgnore([
                    'id' => (string) Str::ulid(),
                    'warehouse_id' => $lockedCount->warehouse_id,
                    'product_id' => $item->product_id,
                    'quantity_on_hand' => '0.000000',
                    'quantity_reserved' => '0.000000',
                    'reorder_point' => '0.000000',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $stock = InventoryStock::query()
                    ->where('warehouse_id', $lockedCount->warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (bccomp((string) $stock->quantity_on_hand, (string) $item->expected_quantity, 6) !== 0) {
                    throw ValidationException::withMessages([
                        'count' => ["La existencia de {$item->product_name} cambió después de iniciar el conteo. Cancela este conteo y genera uno nuevo."],
                    ]);
                }
            }

            $occurredAt = CarbonImmutable::parse($lockedCount->counted_on->toDateString())->startOfDay();

            foreach ($lockedCount->items as $item) {
                $difference = bcsub((string) $item->counted_quantity, (string) $item->expected_quantity, 6);
                $item->forceFill(['difference' => $difference])->save();

                if (bccomp($difference, '0', 6) === 0) {
                    continue;
                }

                $this->applyDelta->handle(
                    $lockedCount->warehouse,
                    $item->product,
                    bccomp($difference, '0', 6) > 0
                        ? InventoryMovementType::PhysicalCountIn
                        : InventoryMovementType::PhysicalCountOut,
                    $difference,
                    '0.000000',
                    $actor,
                    [
                        'inventory_count_id' => $lockedCount->id,
                        'occurred_at' => $occurredAt,
                        'product_sku' => $item->product_sku,
                        'product_name' => $item->product_name,
                        'base_unit_symbol' => $item->base_unit_symbol,
                        'reference_number' => $lockedCount->count_number,
                        'reason' => $item->notes ?? 'Diferencia detectada en conteo físico.',
                    ],
                );
            }

            $lockedCount->forceFill([
                'status' => InventoryCountStatus::Posted,
                'posted_at' => now(),
                'posted_by' => $actor->id,
            ])->save();

            return $lockedCount->refresh();
        }, 3);
    }
}
