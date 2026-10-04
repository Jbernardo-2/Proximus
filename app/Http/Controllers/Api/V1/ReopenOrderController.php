<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ReopenOrderAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\TransitionOrderRequest;
use App\Http\Resources\Api\V1\OrderResource;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class ReopenOrderController extends Controller
{
    public function __invoke(
        TransitionOrderRequest $request,
        Order $order,
        ReopenOrderAction $reopenOrder,
    ): OrderResource {
        Gate::authorize('reopen', $order);
        /** @var User $user */
        $user = $request->user();
        $reopenedOrder = $reopenOrder->handle($order, $user, $request->string('reason')->toString());

        return new OrderResource($reopenedOrder->load('creator')->loadCount('items'));
    }
}
