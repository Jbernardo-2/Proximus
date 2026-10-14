<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CompleteDeliveryStopAction;
use App\Actions\RecordCustomerVisitAction;
use App\CustomerVisitType;
use App\Http\Controllers\Controller;
use App\Http\Requests\CompleteDeliveryStopRequest;
use App\Http\Resources\Api\V1\DeliveryRunOrderResource;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunOrder;
use App\Models\User;
use App\Services\VisitLocation;

class CompleteDeliveryStopController extends Controller
{
    public function __invoke(
        CompleteDeliveryStopRequest $request,
        DeliveryRun $deliveryRun,
        DeliveryRunOrder $runOrder,
        CompleteDeliveryStopAction $completeStop,
        RecordCustomerVisitAction $recordVisit,
    ): DeliveryRunOrderResource {
        /** @var User $user */
        $user = $request->user();
        $location = VisitLocation::fromRequest($request);
        $completed = $completeStop->handle(
            $deliveryRun,
            $runOrder,
            [
                ...$request->safe()->except('items'),
                'latitude' => $request->validated('latitude') ?? $location->latitude,
                'longitude' => $request->validated('longitude') ?? $location->longitude,
            ],
            $request->validated('items'),
            $user,
        );
        $recordVisit->forRequest($request, CustomerVisitType::Delivery, $completed->order->customer_id, $completed);

        return new DeliveryRunOrderResource($completed->load([
            'order', 'items', 'payments.receivedBy', 'payments.voidedBy', 'completedBy',
            'outcomeRecordedBy', 'settlement.lines.deliveryPayment', 'settlement.lines.receivable',
        ]));
    }
}
