<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CompleteDeliveryStopAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\CompleteDeliveryStopRequest;
use App\Http\Resources\Api\V1\DeliveryRunOrderResource;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunOrder;
use App\Models\User;

class CompleteDeliveryStopController extends Controller
{
    public function __invoke(
        CompleteDeliveryStopRequest $request,
        DeliveryRun $deliveryRun,
        DeliveryRunOrder $runOrder,
        CompleteDeliveryStopAction $completeStop,
    ): DeliveryRunOrderResource {
        /** @var User $user */
        $user = $request->user();
        $completed = $completeStop->handle(
            $deliveryRun,
            $runOrder,
            $request->safe()->except('items'),
            $request->validated('items'),
            $user,
        );

        return new DeliveryRunOrderResource($completed->load([
            'order', 'items', 'payments.receivedBy', 'payments.voidedBy', 'completedBy',
        ]));
    }
}
