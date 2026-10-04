<?php

namespace App\Http\Controllers;

use App\Actions\ConfirmOrderAction;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ConfirmOrderController extends Controller
{
    public function __invoke(Request $request, Order $order, ConfirmOrderAction $confirmOrder): RedirectResponse
    {
        Gate::authorize('confirm', $order);
        /** @var User $user */
        $user = $request->user();
        $confirmOrder->handle($order, $user);

        return redirect()->route('orders.show', $order)->with('success', 'Pedido confirmado y enviado a bodega.');
    }
}
