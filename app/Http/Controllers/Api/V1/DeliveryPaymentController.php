<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RecordDeliveryPaymentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeliveryPaymentRequest;
use App\Http\Resources\Api\V1\DeliveryPaymentResource;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunOrder;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DeliveryPaymentController extends Controller
{
    public function store(
        StoreDeliveryPaymentRequest $request,
        DeliveryRun $deliveryRun,
        DeliveryRunOrder $runOrder,
        RecordDeliveryPaymentAction $recordPayment,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $payment = $recordPayment->handle($deliveryRun, $runOrder, $request->validated(), $user);

        return (new DeliveryPaymentResource($payment->load(['receivedBy', 'voidedBy'])))
            ->response()
            ->setStatusCode(201);
    }
}
