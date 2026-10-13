<?php

namespace App\Http\Controllers;

use App\Actions\ConfirmOrderAction;
use App\Http\Requests\ConfirmOrderRequest;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class ConfirmOrderController extends Controller
{
    public function __invoke(
        ConfirmOrderRequest $request,
        Order $order,
        ConfirmOrderAction $confirmOrder,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $confirmOrder->handle($order, $user, $request->validated('credit_override_reason'));

        return redirect()->route('orders.index')->with('success', 'Pedido confirmado y enviado a bodega.');
    }
}
