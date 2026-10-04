<?php

namespace App\Actions;

use App\DeliveryRunStatus;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunOrder;
use App\Models\Order;
use App\Models\User;
use App\OrderStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemoveOrderFromDeliveryRunAction
{
    public function handle(DeliveryRun $deliveryRun, DeliveryRunOrder $runOrder, User $actor): void
    {
        DB::transaction(function () use ($deliveryRun, $runOrder, $actor): void {
            $lockedRun = DeliveryRun::query()->lockForUpdate()->findOrFail($deliveryRun->id);

            if ($lockedRun->status !== DeliveryRunStatus::Draft) {
                throw ValidationException::withMessages([
                    'delivery_run' => ['Solo puedes retirar pedidos de una jornada en borrador.'],
                ]);
            }

            $lockedRunOrder = $lockedRun->runOrders()->lockForUpdate()->findOrFail($runOrder->id);
            $order = Order::query()->lockForUpdate()->findOrFail($lockedRunOrder->order_id);

            if ($order->status !== OrderStatus::Assigned) {
                throw ValidationException::withMessages([
                    'order' => ['El pedido ya avanzó y no puede retirarse de la jornada.'],
                ]);
            }

            $lockedRunOrder->delete();
            $order->forceFill(['status' => OrderStatus::Confirmed])->save();
            $order->statusHistory()->create([
                'from_status' => OrderStatus::Assigned,
                'to_status' => OrderStatus::Confirmed,
                'changed_by' => $actor->id,
                'reason' => "Retirado de la jornada {$lockedRun->run_number} antes de preparar la carga.",
            ]);
        });
    }
}
