<?php

namespace App\Actions;

use App\Models\Order;
use App\Models\User;
use App\OrderStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelOrderAction
{
    public function __construct(private ReleaseOrderStockAction $releaseStock) {}

    public function handle(Order $order, User $actor, string $reason): Order
    {
        return DB::transaction(function () use ($order, $actor, $reason): Order {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! in_array($lockedOrder->status, [OrderStatus::Draft, OrderStatus::Confirmed], true)) {
                throw ValidationException::withMessages([
                    'order' => ['Solo puedes cancelar pedidos en borrador o confirmados que todavía no estén asignados a reparto.'],
                ]);
            }

            $previousStatus = $lockedOrder->status;

            if ($previousStatus === OrderStatus::Confirmed) {
                $this->releaseStock->handle($lockedOrder, $actor, 'Reserva liberada por cancelación del pedido: '.$reason);
            }

            $lockedOrder->forceFill([
                'status' => OrderStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
                'cancellation_reason' => $reason,
            ])->save();
            $lockedOrder->statusHistory()->create([
                'from_status' => $previousStatus,
                'to_status' => OrderStatus::Cancelled,
                'changed_by' => $actor->id,
                'reason' => $reason,
            ]);

            return $lockedOrder;
        });
    }
}
