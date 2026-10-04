<?php

namespace App\Actions;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemoveOrderItemAction
{
    public function __construct(private RecalculateOrderTotalsAction $recalculateTotals) {}

    public function handle(Order $order, OrderItem $item): void
    {
        DB::transaction(function () use ($order, $item): void {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! $lockedOrder->isDraft()) {
                throw ValidationException::withMessages([
                    'order' => ['Solo los pedidos en borrador se pueden editar.'],
                ]);
            }

            $lockedOrder->items()->findOrFail($item->id)->delete();
            $this->recalculateTotals->handle($lockedOrder);
        });
    }
}
