<?php

namespace App\Actions;

use App\DeliveryRunStatus;
use App\Models\DeliveryRun;
use App\Models\Order;
use App\Models\User;
use App\OrderStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DepartDeliveryRunAction
{
    public function handle(DeliveryRun $deliveryRun, User $actor): DeliveryRun
    {
        return DB::transaction(function () use ($deliveryRun, $actor): DeliveryRun {
            $run = DeliveryRun::query()->lockForUpdate()->findOrFail($deliveryRun->id);

            if ($run->status !== DeliveryRunStatus::Loaded) {
                throw ValidationException::withMessages([
                    'delivery_run' => ['Solo una jornada cargada puede iniciar la ruta.'],
                ]);
            }

            foreach ($run->runOrders()->orderBy('visit_order')->lockForUpdate()->get() as $runOrder) {
                $order = Order::query()->lockForUpdate()->findOrFail($runOrder->order_id);

                if ($order->status !== OrderStatus::Loaded) {
                    throw ValidationException::withMessages([
                        'orders' => ["El pedido {$order->order_number} no está listo para salir."],
                    ]);
                }

                $order->forceFill(['status' => OrderStatus::InTransit])->save();
                $order->statusHistory()->create([
                    'from_status' => OrderStatus::Loaded,
                    'to_status' => OrderStatus::InTransit,
                    'changed_by' => $actor->id,
                    'reason' => "Salida de la jornada {$run->run_number}.",
                ]);
            }

            $run->forceFill([
                'status' => DeliveryRunStatus::InTransit,
                'departed_at' => now(),
                'departed_by' => $actor->id,
            ])->save();
            $run->statusHistory()->create([
                'from_status' => DeliveryRunStatus::Loaded,
                'to_status' => DeliveryRunStatus::InTransit,
                'changed_by' => $actor->id,
                'reason' => 'El repartidor inició la jornada.',
            ]);

            return $run;
        });
    }
}
