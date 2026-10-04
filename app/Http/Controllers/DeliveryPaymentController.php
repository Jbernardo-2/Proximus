<?php

namespace App\Http\Controllers;

use App\Actions\RecordDeliveryPaymentAction;
use App\Http\Requests\StoreDeliveryPaymentRequest;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunOrder;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class DeliveryPaymentController extends Controller
{
    public function store(
        StoreDeliveryPaymentRequest $request,
        DeliveryRun $deliveryRun,
        DeliveryRunOrder $runOrder,
        RecordDeliveryPaymentAction $recordPayment,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $payment = $recordPayment->handle($deliveryRun, $runOrder, $request->validated(), $user);

        return redirect()->route('delivery-runs.show', $deliveryRun)
            ->with('success', "Cobro {$payment->receipt_number} registrado.");
    }
}
