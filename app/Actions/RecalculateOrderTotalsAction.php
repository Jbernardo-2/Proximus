<?php

namespace App\Actions;

use App\Models\Order;

class RecalculateOrderTotalsAction
{
    public function handle(Order $order): void
    {
        $total = '0.0000';

        foreach ($order->items()->pluck('line_total') as $lineTotal) {
            $total = bcadd($total, (string) $lineTotal, 4);
        }

        $order->forceFill([
            'subtotal' => $total,
            'total' => $total,
        ])->save();
    }
}
