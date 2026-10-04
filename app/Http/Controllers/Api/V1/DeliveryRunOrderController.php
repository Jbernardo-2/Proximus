<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\AssignOrderToDeliveryRunAction;
use App\Actions\RemoveOrderFromDeliveryRunAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignDeliveryRunOrderRequest;
use App\Http\Resources\Api\V1\DeliveryRunOrderResource;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunOrder;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DeliveryRunOrderController extends Controller
{
    public function store(
        AssignDeliveryRunOrderRequest $request,
        DeliveryRun $deliveryRun,
        AssignOrderToDeliveryRunAction $assignOrder,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $order = Order::query()->findOrFail($request->validated('order_id'));
        $runOrder = $assignOrder->handle(
            $deliveryRun,
            $order,
            $user,
            $request->integer('visit_order') ?: null,
        );

        return (new DeliveryRunOrderResource($runOrder->load(['order', 'items', 'payments'])))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(
        Request $request,
        DeliveryRun $deliveryRun,
        DeliveryRunOrder $runOrder,
        RemoveOrderFromDeliveryRunAction $removeOrder,
    ): JsonResponse {
        Gate::authorize('assignOrders', $deliveryRun);
        abort_unless($runOrder->delivery_run_id === $deliveryRun->id, 404);
        /** @var User $user */
        $user = $request->user();
        $removeOrder->handle($deliveryRun, $runOrder, $user);

        return response()->json(status: 204);
    }
}
