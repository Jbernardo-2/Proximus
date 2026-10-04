<?php

namespace App\Http\Controllers;

use App\Actions\VoidDeliveryPaymentAction;
use App\Http\Requests\VoidDeliveryPaymentRequest;
use App\Models\DeliveryPayment;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunOrder;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class VoidDeliveryPaymentController extends Controller
{
    public function __invoke(
        VoidDeliveryPaymentRequest $request,
        DeliveryRun $deliveryRun,
        DeliveryRunOrder $runOrder,
        DeliveryPayment $payment,
        VoidDeliveryPaymentAction $voidPayment,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $voidPayment->handle($deliveryRun, $runOrder, $payment, $request->string('reason')->toString(), $user);

        return redirect()->route('delivery-runs.show', $deliveryRun)
            ->with('success', 'Cobro anulado; el saldo de la entrega fue recalculado.');
    }
}
