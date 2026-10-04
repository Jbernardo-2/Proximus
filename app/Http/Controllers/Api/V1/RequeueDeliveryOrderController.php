<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RequeueDeliveryOrderAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\RequeueDeliveryOrderRequest;
use App\Http\Resources\Api\V1\OrderResource;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunOrder;
use App\Models\User;

class RequeueDeliveryOrderController extends Controller
{
    public function __invoke(
        RequeueDeliveryOrderRequest $request,
        DeliveryRun $deliveryRun,
        DeliveryRunOrder $runOrder,
        RequeueDeliveryOrderAction $requeueOrder,
    ): OrderResource {
        /** @var User $user */
        $user = $request->user();
        abort_unless($runOrder->delivery_run_id === $deliveryRun->id, 404);
        $order = $requeueOrder->handle(
            $runOrder,
            $request->string('reason')->toString(),
            $user,
        );

        return new OrderResource($order->load([
            'customer', 'salesRoute', 'routeStop', 'salesperson', 'creator', 'warehouse', 'items', 'statusHistory.changedBy',
        ]));
    }
}
