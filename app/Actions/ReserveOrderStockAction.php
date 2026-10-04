<?php

namespace App\Actions;

use App\InventoryMovementType;
use App\InventoryReservationStatus;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReserveOrderStockAction
{
    public function __construct(private ApplyInventoryDeltaAction $applyDelta) {}

    public function handle(Order $order, User $actor): void
    {
        DB::transaction(function () use ($order, $actor): void {
            if ($order->warehouse_id === null) {
                throw ValidationException::withMessages([
                    'warehouse_id' => ['Selecciona una bodega antes de confirmar el pedido.'],
                ]);
            }

            $warehouse = Warehouse::query()->lockForUpdate()->findOrFail($order->warehouse_id);

            if (! $warehouse->is_active) {
                throw ValidationException::withMessages([
                    'warehouse_id' => ['La bodega asignada al pedido ya no está activa.'],
                ]);
            }

            $order->load(['items.product']);

            foreach ($order->items as $item) {
                $existing = InventoryReservation::query()
                    ->where('order_item_id', $item->id)
                    ->lockForUpdate()
                    ->first();

                if ($existing?->status === InventoryReservationStatus::Active) {
                    throw ValidationException::withMessages([
                        'order' => ['El pedido ya tiene reservas activas de inventario.'],
                    ]);
                }

                $this->applyDelta->handle(
                    $warehouse,
                    $item->product,
                    InventoryMovementType::OrderReservation,
                    '0.000000',
                    (string) $item->base_quantity,
                    $actor,
                    [
                        'product_presentation_id' => $item->product_presentation_id,
                        'order_id' => $order->id,
                        'order_item_id' => $item->id,
                        'presentation_quantity' => $item->quantity,
                        'conversion_factor' => $item->conversion_factor,
                        'product_sku' => $item->product_sku,
                        'product_name' => $item->product_name,
                        'presentation_name' => $item->presentation_name,
                        'base_unit_symbol' => $item->base_unit_symbol,
                        'reference_number' => $order->order_number,
                        'reason' => 'Mercancía comprometida al confirmar el pedido.',
                    ],
                );

                InventoryReservation::query()->updateOrCreate(
                    ['order_item_id' => $item->id],
                    [
                        'order_id' => $order->id,
                        'warehouse_id' => $warehouse->id,
                        'product_id' => $item->product_id,
                        'base_quantity' => $item->base_quantity,
                        'status' => InventoryReservationStatus::Active,
                        'reserved_at' => now(),
                        'released_at' => null,
                        'created_by' => $actor->id,
                        'released_by' => null,
                    ],
                );
            }
        });
    }
}
