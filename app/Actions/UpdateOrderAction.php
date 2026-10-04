<?php

namespace App\Actions;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateOrderAction
{
    /** @param array<string, mixed> $data */
    public function handle(Order $order, array $data): Order
    {
        return DB::transaction(function () use ($order, $data): Order {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! $lockedOrder->isDraft()) {
                throw ValidationException::withMessages([
                    'order' => ['Solo los pedidos en borrador se pueden editar.'],
                ]);
            }

            $lockedOrder->update([
                'payment_term' => $data['payment_term'],
                'requested_delivery_date' => $data['requested_delivery_date'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            return $lockedOrder;
        });
    }
}
