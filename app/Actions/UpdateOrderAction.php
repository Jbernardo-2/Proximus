<?php

namespace App\Actions;

use App\Models\Order;
use App\Models\Warehouse;
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

            $warehouse = isset($data['warehouse_id'])
                ? Warehouse::query()->active()->findOrFail($data['warehouse_id'])
                : Warehouse::query()->active()->find($lockedOrder->warehouse_id);

            if ($warehouse === null || ! $warehouse->is_active) {
                throw ValidationException::withMessages([
                    'warehouse_id' => ['Selecciona una bodega activa.'],
                ]);
            }

            $lockedOrder->update([
                'warehouse_id' => $warehouse->id,
                'warehouse_code' => $warehouse->code,
                'warehouse_name' => $warehouse->name,
                'payment_term' => $data['payment_term'],
                'requested_delivery_date' => $data['requested_delivery_date'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            return $lockedOrder;
        });
    }
}
