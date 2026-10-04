<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CancelOrderAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\TransitionOrderRequest;
use App\Http\Resources\Api\V1\OrderResource;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class CancelOrderController extends Controller
{
    public function __invoke(
        TransitionOrderRequest $request,
        Order $order,
        CancelOrderAction $cancelOrder,
    ): OrderResource {
        Gate::authorize('cancel', $order);
        /** @var User $user */
        $user = $request->user();
        $cancelledOrder = $cancelOrder->handle($order, $user, $request->string('reason')->toString());

        return new OrderResource($cancelledOrder->load(['creator', 'cancelledBy'])->loadCount('items'));
    }
}
