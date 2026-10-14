<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RecordCustomerVisitAction;
use App\Actions\RecordDeliverySettlementAction;
use App\CustomerVisitType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeliverySettlementRequest;
use App\Http\Resources\Api\V1\DeliveryRunOrderResource;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunOrder;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DeliveryPaymentController extends Controller
{
    public function store(
        StoreDeliverySettlementRequest $request,
        DeliveryRun $deliveryRun,
        DeliveryRunOrder $runOrder,
        RecordDeliverySettlementAction $recordSettlement,
        RecordCustomerVisitAction $recordVisit,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $settlement = $recordSettlement->handle($deliveryRun, $runOrder, $request->validated(), $user);
        $recordVisit->forRequest($request, CustomerVisitType::DeliveryPayment, $runOrder->order()->value('customer_id'), $settlement);
        $runOrder->refresh()->load([
            'order',
            'items',
            'payments.receivedBy',
            'payments.voidedBy',
            'completedBy',
            'outcomeRecordedBy',
            'settlement.lines.deliveryPayment',
            'settlement.lines.receivable',
            'settlement.settledBy',
        ]);

        return (new DeliveryRunOrderResource($runOrder))
            ->response()
            ->setStatusCode(201);
    }
}
