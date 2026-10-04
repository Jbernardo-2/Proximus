<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CancelDeliveryRunAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelDeliveryRunRequest;
use App\Http\Resources\Api\V1\DeliveryRunResource;
use App\Models\DeliveryRun;
use App\Models\User;

class CancelDeliveryRunController extends Controller
{
    public function __invoke(
        CancelDeliveryRunRequest $request,
        DeliveryRun $deliveryRun,
        CancelDeliveryRunAction $cancelDeliveryRun,
    ): DeliveryRunResource {
        /** @var User $user */
        $user = $request->user();
        $cancelled = $cancelDeliveryRun->handle(
            $deliveryRun,
            $request->string('reason')->toString(),
            $user,
        );

        return new DeliveryRunResource($cancelled->load([
            'warehouse', 'driver', 'vehicle', 'runOrders.order', 'runOrders.items', 'runOrders.payments',
        ])->loadCount('runOrders'));
    }
}
