<?php

namespace App\Http\Controllers;

use App\Actions\ReopenOrderAction;
use App\Http\Requests\TransitionOrderRequest;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ReopenOrderController extends Controller
{
    public function __invoke(
        TransitionOrderRequest $request,
        Order $order,
        ReopenOrderAction $reopenOrder,
    ): RedirectResponse {
        Gate::authorize('reopen', $order);
        /** @var User $user */
        $user = $request->user();
        $reopenOrder->handle($order, $user, $request->string('reason')->toString());

        return redirect()->route('orders.show', $order)->with('success', 'Pedido reabierto como borrador.');
    }
}
