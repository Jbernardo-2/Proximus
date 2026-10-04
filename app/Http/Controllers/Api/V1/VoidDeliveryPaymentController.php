<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\VoidDeliveryPaymentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\VoidDeliveryPaymentRequest;
use App\Http\Resources\Api\V1\DeliveryPaymentResource;
use App\Models\DeliveryPayment;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunOrder;
use App\Models\User;

class VoidDeliveryPaymentController extends Controller
{
    public function __invoke(
        VoidDeliveryPaymentRequest $request,
        DeliveryRun $deliveryRun,
        DeliveryRunOrder $runOrder,
        DeliveryPayment $payment,
        VoidDeliveryPaymentAction $voidPayment,
    ): DeliveryPaymentResource {
        /** @var User $user */
        $user = $request->user();
        $voided = $voidPayment->handle(
            $deliveryRun,
            $runOrder,
            $payment,
            $request->string('reason')->toString(),
            $user,
        );

        return new DeliveryPaymentResource($voided->load(['receivedBy', 'voidedBy']));
    }
}
