<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ReopenDeliveryStopAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReopenDeliveryStopRequest;
use App\Http\Resources\Api\V1\DeliveryRunOrderResource;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunOrder;
use App\Models\User;

class ReopenDeliveryStopController extends Controller
{
    public function __invoke(
        ReopenDeliveryStopRequest $request,
        DeliveryRun $deliveryRun,
        DeliveryRunOrder $runOrder,
        ReopenDeliveryStopAction $reopenStop,
    ): DeliveryRunOrderResource {
        /** @var User $user */
        $user = $request->user();
        $reopened = $reopenStop->handle($deliveryRun, $runOrder, $user);

        return new DeliveryRunOrderResource($reopened->load([
            'order', 'items', 'payments.receivedBy', 'payments.voidedBy', 'completedBy',
            'outcomeRecordedBy', 'settlement.lines.deliveryPayment', 'settlement.lines.receivable',
        ]));
    }
}
