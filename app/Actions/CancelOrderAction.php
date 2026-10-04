<?php

namespace App\Actions;

use App\Models\Order;
use App\Models\User;
use App\OrderStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelOrderAction
{
    public function handle(Order $order, User $actor, string $reason): Order
    {
        return DB::transaction(function () use ($order, $actor, $reason): Order {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($lockedOrder->status === OrderStatus::Cancelled) {
                throw ValidationException::withMessages([
                    'order' => ['El pedido ya está cancelado.'],
                ]);
            }

            $previousStatus = $lockedOrder->status;
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
