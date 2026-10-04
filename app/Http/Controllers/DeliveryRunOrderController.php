<?php

namespace App\Http\Controllers;

use App\Actions\AssignOrderToDeliveryRunAction;
use App\Actions\RemoveOrderFromDeliveryRunAction;
use App\Http\Requests\AssignDeliveryRunOrderRequest;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunOrder;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DeliveryRunOrderController extends Controller
{
    public function store(
        AssignDeliveryRunOrderRequest $request,
        DeliveryRun $deliveryRun,
        AssignOrderToDeliveryRunAction $assignOrder,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $order = Order::query()->findOrFail($request->validated('order_id'));
        $assignOrder->handle($deliveryRun, $order, $user, $request->integer('visit_order') ?: null);

        return redirect()->route('delivery-runs.show', $deliveryRun)
            ->with('success', "Pedido {$order->order_number} asignado a la jornada.");
    }

    public function destroy(
        Request $request,
        DeliveryRun $deliveryRun,
        DeliveryRunOrder $runOrder,
        RemoveOrderFromDeliveryRunAction $removeOrder,
    ): RedirectResponse {
        Gate::authorize('assignOrders', $deliveryRun);
        abort_unless($runOrder->delivery_run_id === $deliveryRun->id, 404);
        /** @var User $user */
        $user = $request->user();
        $removeOrder->handle($deliveryRun, $runOrder, $user);

        return redirect()->route('delivery-runs.show', $deliveryRun)
            ->with('success', 'Pedido retirado de la jornada; su reserva permanece activa.');
    }
}
