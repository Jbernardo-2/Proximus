<?php

namespace App\Http\Controllers;

use App\Actions\RemoveOrderItemAction;
use App\Actions\SaveOrderItemAction;
use App\Http\Requests\StoreOrderItemRequest;
use App\Http\Requests\UpdateOrderItemRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OrderItemController extends Controller
{
    public function store(
        StoreOrderItemRequest $request,
        Order $order,
        SaveOrderItemAction $saveOrderItem,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $saveOrderItem->handle(
            $order,
            $request->validated(),
            $user,
            Gate::forUser($user)->allows('overridePrice', $order),
        );

        return redirect()->route('orders.show', $order)->with('success', 'Producto agregado al pedido.');
    }

    public function update(
        UpdateOrderItemRequest $request,
        Order $order,
        OrderItem $orderItem,
        SaveOrderItemAction $saveOrderItem,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $saveOrderItem->handle(
            $order,
            $request->validated(),
            $user,
            Gate::forUser($user)->allows('overridePrice', $order),
            $orderItem,
        );

        return redirect()->route('orders.show', $order)->with('success', 'Línea del pedido actualizada.');
    }

    public function destroy(
        Request $request,
        Order $order,
        OrderItem $orderItem,
        RemoveOrderItemAction $removeOrderItem,
    ): RedirectResponse {
        Gate::authorize('update', $order);
        $removeOrderItem->handle($order, $orderItem);

        return redirect()->route('orders.show', $order)->with('success', 'Producto retirado del pedido.');
    }
}
