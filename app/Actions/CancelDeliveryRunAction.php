<?php

namespace App\Actions;

use App\DeliveryOrderStatus;
use App\DeliveryRunStatus;
use App\Models\DeliveryRun;
use App\Models\Order;
use App\Models\User;
use App\OrderStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelDeliveryRunAction
{
    public function handle(DeliveryRun $deliveryRun, string $reason, User $actor): DeliveryRun
    {
        return DB::transaction(function () use ($deliveryRun, $reason, $actor): DeliveryRun {
            $run = DeliveryRun::query()->lockForUpdate()->findOrFail($deliveryRun->id);

            if (! in_array($run->status, [DeliveryRunStatus::Draft, DeliveryRunStatus::Preparing], true)) {
                throw ValidationException::withMessages([
                    'delivery_run' => ['Una jornada cargada o finalizada ya no puede cancelarse.'],
                ]);
            }

            $previousStatus = $run->status;

            foreach ($run->runOrders()->with('order')->orderBy('visit_order')->lockForUpdate()->get() as $runOrder) {
                $order = Order::query()->lockForUpdate()->findOrFail($runOrder->order_id);

                if ($order->status !== OrderStatus::Assigned) {
                    throw ValidationException::withMessages([
                        'orders' => ["El pedido {$order->order_number} ya avanzó y la jornada no puede cancelarse."],
                    ]);
                }

                $runOrder->forceFill(['status' => DeliveryOrderStatus::Cancelled])->save();
                $order->forceFill(['status' => OrderStatus::Confirmed])->save();
                $order->statusHistory()->create([
                    'from_status' => OrderStatus::Assigned,
                    'to_status' => OrderStatus::Confirmed,
                    'changed_by' => $actor->id,
                    'reason' => "Jornada {$run->run_number} cancelada: {$reason}",
                ]);
            }

            $run->forceFill([
                'status' => DeliveryRunStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
                'cancellation_reason' => $reason,
            ])->save();
            $run->statusHistory()->create([
                'from_status' => $previousStatus,
                'to_status' => DeliveryRunStatus::Cancelled,
                'changed_by' => $actor->id,
                'reason' => $reason,
            ]);

            return $run;
        });
    }
}
