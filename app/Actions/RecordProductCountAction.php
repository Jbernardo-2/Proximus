<?php

namespace App\Actions;

use App\InventoryCountStatus;
use App\InventoryMovementType;
use App\Models\InventoryCount;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\OperationalNotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * One product counted at a moment, possibly hours before it reaches the server.
 * The difference is taken against the stock at that moment, so what moved after
 * the count (a load, a receipt) does not turn into a false difference.
 */
class RecordProductCountAction
{
    public function __construct(
        private GenerateInventoryNumberAction $generateNumber,
        private ApplyInventoryDeltaAction $applyDelta,
        private OperationalNotificationService $notifications,
    ) {}

    /** @param array{warehouse_id: string, product_id: string, counted_quantity: string, counted_at?: ?string, notes?: ?string} $data */
    public function handle(array $data, User $actor): InventoryCount
    {
        $count = DB::transaction(function () use ($data, $actor): InventoryCount {
            $warehouse = Warehouse::query()->active()->findOrFail($data['warehouse_id']);
            $product = Product::query()->withTrashed()->with('baseUnit')->findOrFail($data['product_id']);
            $countedAt = isset($data['counted_at']) ? CarbonImmutable::parse($data['counted_at']) : CarbonImmutable::now();

            if (! $product->allows_decimal && bccomp((string) $data['counted_quantity'], bcadd((string) $data['counted_quantity'], '0', 0), 6) !== 0) {
                throw ValidationException::withMessages([
                    'counted_quantity' => ["{$product->name} se cuenta en unidades enteras."],
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
            $movedSince = (string) InventoryMovement::query()
                ->where('warehouse_id', $warehouse->id)
                ->where('product_id', $product->id)
                ->where('occurred_at', '>', $countedAt)
                ->sum('quantity_on_hand_delta');
            $expected = bcsub((string) $stock->quantity_on_hand, $movedSince, 6);
            $counted = bcadd((string) $data['counted_quantity'], '0', 6);
            $difference = bcsub($counted, $expected, 6);

            $count = InventoryCount::query()->create([
                'count_number' => $this->generateNumber->handle('physical-count', 'CON', $countedAt->startOfDay()),
                'warehouse_id' => $warehouse->id,
                'status' => InventoryCountStatus::Posted,
                'counted_on' => $countedAt->startOfDay(),
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
                'posted_at' => now(),
                'posted_by' => $actor->id,
            ]);
            $count->items()->create([
                'product_id' => $product->id,
                'product_sku' => $product->sku,
                'product_name' => $product->name,
                'base_unit_symbol' => $product->baseUnit->symbol,
                'expected_quantity' => $expected,
                'counted_quantity' => $counted,
                'difference' => $difference,
                'notes' => $data['notes'] ?? null,
            ]);

            if (bccomp($difference, '0', 6) !== 0) {
                $this->applyDelta->handle(
                    $warehouse,
                    $product,
                    bccomp($difference, '0', 6) > 0 ? InventoryMovementType::PhysicalCountIn : InventoryMovementType::PhysicalCountOut,
                    $difference,
                    '0.000000',
                    $actor,
                    [
                        'inventory_count_id' => $count->id,
                        'occurred_at' => $countedAt,
                        'product_sku' => $product->sku,
                        'product_name' => $product->name,
                        'base_unit_symbol' => $product->baseUnit->symbol,
                        'reference_number' => $count->count_number,
                        'reason' => $data['notes'] ?? 'Diferencia detectada en conteo por producto.',
                    ],
                );
            }

            return $count->load(['warehouse', 'items']);
        });

        $this->notifications->productCountAdjusted($count, $actor);

        return $count;
    }
}
