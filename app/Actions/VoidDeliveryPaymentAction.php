<?php

namespace App\Actions;

use App\DeliveryPaymentStatus;
use App\DeliveryRunStatus;
use App\Models\DeliveryPayment;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoidDeliveryPaymentAction
{
    public function __construct(private RefreshDeliveryFinancialsAction $refreshFinancials) {}

    public function handle(
        DeliveryRun $deliveryRun,
        DeliveryRunOrder $runOrder,
        DeliveryPayment $payment,
        string $reason,
        User $actor,
    ): DeliveryPayment {
        return DB::transaction(function () use ($deliveryRun, $runOrder, $payment, $reason, $actor): DeliveryPayment {
            $run = DeliveryRun::query()->lockForUpdate()->findOrFail($deliveryRun->id);

            if (! in_array($run->status, [DeliveryRunStatus::InTransit, DeliveryRunStatus::AwaitingSettlement], true)) {
                throw ValidationException::withMessages([
                    'delivery_run' => ['No se pueden anular cobros de una jornada cerrada.'],
                ]);
            }

            $lockedRunOrder = $run->runOrders()->lockForUpdate()->findOrFail($runOrder->id);
            $lockedPayment = $lockedRunOrder->payments()->lockForUpdate()->findOrFail($payment->id);

            if ($lockedPayment->status !== DeliveryPaymentStatus::Active) {
                throw ValidationException::withMessages([
                    'payment' => ['El cobro ya está anulado.'],
                ]);
            }

            $lockedPayment->forceFill([
                'status' => DeliveryPaymentStatus::Voided,
                'voided_at' => now(),
                'voided_by' => $actor->id,
                'void_reason' => $reason,
            ])->save();
            $this->refreshFinancials->handle($run);

            return $lockedPayment->refresh();
        });
    }
}
