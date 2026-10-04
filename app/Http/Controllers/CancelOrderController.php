<?php

namespace App\Http\Controllers;

use App\Actions\CancelOrderAction;
use App\Http\Requests\TransitionOrderRequest;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class CancelOrderController extends Controller
{
    public function __invoke(
        TransitionOrderRequest $request,
        Order $order,
        CancelOrderAction $cancelOrder,
    ): RedirectResponse {
        Gate::authorize('cancel', $order);
        /** @var User $user */
        $user = $request->user();
        $cancelOrder->handle($order, $user, $request->string('reason')->toString());

        return redirect()->route('orders.show', $order)->with('success', 'Pedido cancelado.');
    }
}
