<?php

namespace App\Actions;

use App\InventoryMovementType;
use App\InventoryReservationStatus;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReleaseOrderStockAction
{
    public function __construct(private ApplyInventoryDeltaAction $applyDelta) {}

    public function handle(Order $order, User $actor, string $reason): void
    {
        DB::transaction(function () use ($order, $actor, $reason): void {
            $reservations = InventoryReservation::query()
                ->with(['warehouse', 'product', 'orderItem'])
                ->where('order_id', $order->id)
                ->where('status', InventoryReservationStatus::Active->value)
                ->lockForUpdate()
                ->get();

            foreach ($reservations as $reservation) {
                $item = $reservation->orderItem;

                $this->applyDelta->handle(
                    $reservation->warehouse,
                    $reservation->product,
                    InventoryMovementType::OrderReservationRelease,
                    '0.000000',
                    bcmul((string) $reservation->base_quantity, '-1', 6),
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
                        'reason' => $reason,
                    ],
                );

                $reservation->forceFill([
                    'status' => InventoryReservationStatus::Released,
                    'released_at' => now(),
                    'released_by' => $actor->id,
                    'fulfilled_at' => null,
                    'fulfilled_by' => null,
                ])->save();
            }
        });
    }
}
