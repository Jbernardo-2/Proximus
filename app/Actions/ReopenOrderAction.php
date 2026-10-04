<?php

namespace App\Actions;

use App\Models\Order;
use App\Models\User;
use App\OrderStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReopenOrderAction
{
    public function handle(Order $order, User $actor, string $reason): Order
    {
        return DB::transaction(function () use ($order, $actor, $reason): Order {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! in_array($lockedOrder->status, [OrderStatus::Confirmed, OrderStatus::Cancelled], true)) {
                throw ValidationException::withMessages([
                    'order' => ['Solo se puede reabrir un pedido confirmado o cancelado.'],
                ]);
            }

            $previousStatus = $lockedOrder->status;
            $lockedOrder->forceFill([
                'status' => OrderStatus::Draft,
                'confirmed_at' => null,
                'confirmed_by' => null,
                'cancelled_at' => null,
                'cancelled_by' => null,
                'cancellation_reason' => null,
            ])->save();
            $lockedOrder->statusHistory()->create([
                'from_status' => $previousStatus,
                'to_status' => OrderStatus::Draft,
                'changed_by' => $actor->id,
                'reason' => $reason,
            ]);

            return $lockedOrder;
        });
    }
}
