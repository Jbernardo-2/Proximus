<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RemoveOrderItemAction;
use App\Actions\SaveOrderItemAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderItemRequest;
use App\Http\Requests\UpdateOrderItemRequest;
use App\Http\Resources\Api\V1\OrderItemResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OrderItemController extends Controller
{
    public function store(
        StoreOrderItemRequest $request,
        Order $order,
        SaveOrderItemAction $saveOrderItem,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $item = $saveOrderItem->handle(
            $order,
            $request->validated(),
            $user,
            Gate::forUser($user)->allows('overridePrice', $order) && $user->tokenCan('orders:override'),
        );

        return (new OrderItemResource($item->load('priceOverriddenBy')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateOrderItemRequest $request,
        Order $order,
        OrderItem $orderItem,
        SaveOrderItemAction $saveOrderItem,
    ): OrderItemResource {
        /** @var User $user */
        $user = $request->user();
        $item = $saveOrderItem->handle(
            $order,
            $request->validated(),
            $user,
            Gate::forUser($user)->allows('overridePrice', $order) && $user->tokenCan('orders:override'),
            $orderItem,
        );

        return new OrderItemResource($item->load('priceOverriddenBy'));
    }

    public function destroy(
        Request $request,
        Order $order,
        OrderItem $orderItem,
        RemoveOrderItemAction $removeOrderItem,
    ): JsonResponse {
        Gate::authorize('update', $order);
        $removeOrderItem->handle($order, $orderItem);

        return response()->json(null, 204);
    }
}
