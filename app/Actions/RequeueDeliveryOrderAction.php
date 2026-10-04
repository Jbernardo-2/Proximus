<?php

namespace App\Actions;

use App\DeliveryOrderStatus;
use App\DeliveryRunStatus;
use App\Models\DeliveryRunOrder;
use App\Models\Order;
use App\Models\User;
use App\OrderStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequeueDeliveryOrderAction
{
    public function __construct(private ReserveOrderStockAction $reserveStock) {}

    public function handle(DeliveryRunOrder $runOrder, string $reason, User $actor): Order
    {
        return DB::transaction(function () use ($runOrder, $reason, $actor): Order {
            $lockedRunOrder = DeliveryRunOrder::query()
                ->with('deliveryRun')
                ->lockForUpdate()
                ->findOrFail($runOrder->id);

            if ($lockedRunOrder->deliveryRun->status !== DeliveryRunStatus::Settled
                || $lockedRunOrder->status !== DeliveryOrderStatus::NotDelivered) {
                throw ValidationException::withMessages([
                    'delivery_run_order' => ['Solo se puede reprogramar un pedido no entregado de una jornada liquidada.'],
                ]);
            }

            $order = Order::query()->lockForUpdate()->findOrFail($lockedRunOrder->order_id);

            if ($order->status !== OrderStatus::NotDelivered) {
                throw ValidationException::withMessages([
                    'order' => ['El pedido ya fue reprogramado o cambió de estado.'],
                ]);
            }

            $this->reserveStock->handle($order, $actor);
            $order->forceFill(['status' => OrderStatus::Confirmed])->save();
            $order->statusHistory()->create([
                'from_status' => OrderStatus::NotDelivered,
                'to_status' => OrderStatus::Confirmed,
                'changed_by' => $actor->id,
                'reason' => 'Pedido reprogramado para una nueva jornada: '.$reason,
            ]);

            return $order;
        });
    }
}
