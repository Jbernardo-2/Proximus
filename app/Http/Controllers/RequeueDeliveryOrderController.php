<?php

namespace App\Http\Controllers;

use App\Actions\RequeueDeliveryOrderAction;
use App\Http\Requests\RequeueDeliveryOrderRequest;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunOrder;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class RequeueDeliveryOrderController extends Controller
{
    public function __invoke(
        RequeueDeliveryOrderRequest $request,
        DeliveryRun $deliveryRun,
        DeliveryRunOrder $runOrder,
        RequeueDeliveryOrderAction $requeueOrder,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        abort_unless($runOrder->delivery_run_id === $deliveryRun->id, 404);
        $order = $requeueOrder->handle($runOrder, $request->string('reason')->toString(), $user);

        return redirect()->route('orders.show', $order)
            ->with('success', 'Pedido reprogramado y reservado nuevamente para otra jornada.');
    }
}
