<?php

namespace App\Actions;

use App\DeliveryOrderStatus;
use App\DeliveryPaymentStatus;
use App\DeliveryRunStatus;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunOrder;
use App\Models\Order;
use App\Models\User;
use App\OrderStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReopenDeliveryStopAction
{
    public function __construct(
        private RefreshDeliveryFinancialsAction $refreshFinancials,
    ) {}

    public function handle(DeliveryRun $deliveryRun, DeliveryRunOrder $runOrder, User $actor): DeliveryRunOrder
    {
        return DB::transaction(function () use ($deliveryRun, $runOrder, $actor): DeliveryRunOrder {
            $run = DeliveryRun::query()->lockForUpdate()->findOrFail($deliveryRun->id);

            if (! $run->canRecordRouteActivity()) {
                throw ValidationException::withMessages([
                    'delivery_run' => ['La jornada ya no permite corregir resultados de entrega.'],
                ]);
            }

            $lockedRunOrder = $run->runOrders()->lockForUpdate()->findOrFail($runOrder->id);
            $hasOutcome = $lockedRunOrder->status === DeliveryOrderStatus::AwaitingPayment
                || $lockedRunOrder->status->isCompleted();

            if (! $hasOutcome) {
                throw ValidationException::withMessages([
                    'delivery_run_order' => ['Esta parada todavía no tiene un resultado guardado.'],
                ]);
            }

            if ($lockedRunOrder->settlement()->exists()
                || $lockedRunOrder->payments()->where('status', DeliveryPaymentStatus::Active)->exists()) {
                throw ValidationException::withMessages([
                    'delivery_run_order' => ['La entrega ya tiene un cobro registrado y su resultado no puede corregirse.'],
                ]);
            }

            $lockedRunOrder->forceFill([
                'status' => DeliveryOrderStatus::Loaded,
                'outcome_status' => null,
                'delivered_total' => '0.0000',
                'collected_total' => '0.0000',
                'balance_due' => '0.0000',
                'outcome_recorded_at' => null,
                'outcome_recorded_by' => null,
                'completed_at' => null,
                'completed_by' => null,
            ])->save();

            $order = Order::query()->lockForUpdate()->findOrFail($lockedRunOrder->order_id);

            if ($order->status !== OrderStatus::InTransit) {
                $previousStatus = $order->status;
                $order->forceFill(['status' => OrderStatus::InTransit])->save();
                $order->statusHistory()->create([
                    'from_status' => $previousStatus,
                    'to_status' => OrderStatus::InTransit,
                    'changed_by' => $actor->id,
                    'reason' => "Resultado de entrega reabierto para corrección en la jornada {$run->run_number}.",
                ]);
            }

            if ($run->status === DeliveryRunStatus::AwaitingSettlement) {
                $run->forceFill(['status' => DeliveryRunStatus::InTransit])->save();
                $run->statusHistory()->create([
                    'from_status' => DeliveryRunStatus::AwaitingSettlement,
                    'to_status' => DeliveryRunStatus::InTransit,
                    'changed_by' => $actor->id,
                    'reason' => "Se reabrió la parada #{$lockedRunOrder->visit_order} para corregir su resultado.",
                ]);
            }

            $this->refreshFinancials->handle($run);

            return $lockedRunOrder->refresh();
        }, 3);
    }
}
