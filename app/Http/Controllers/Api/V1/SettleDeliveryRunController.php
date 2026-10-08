<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\SettleDeliveryRunAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\SettleDeliveryRunRequest;
use App\Http\Resources\Api\V1\DeliveryRunResource;
use App\Models\DeliveryRun;
use App\Models\User;

class SettleDeliveryRunController extends Controller
{
    public function __invoke(
        SettleDeliveryRunRequest $request,
        DeliveryRun $deliveryRun,
        SettleDeliveryRunAction $settleDeliveryRun,
    ): DeliveryRunResource {
        /** @var User $user */
        $user = $request->user();
        $settled = $settleDeliveryRun->handle($deliveryRun, $request->validated(), $user);

        return new DeliveryRunResource($settled->load([
            'warehouse', 'driver', 'vehicle', 'runOrders.order', 'runOrders.items', 'runOrders.preparedBy',
            'runOrders.payments.receivedBy', 'runOrders.payments.voidedBy',
        ])->loadCount('runOrders'));
    }
}
