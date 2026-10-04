<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ConfirmOrderAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OrderResource;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ConfirmOrderController extends Controller
{
    public function __invoke(Request $request, Order $order, ConfirmOrderAction $confirmOrder): OrderResource
    {
        Gate::authorize('confirm', $order);
        /** @var User $user */
        $user = $request->user();
        $confirmedOrder = $confirmOrder->handle($order, $user);

        return new OrderResource($confirmedOrder->load([
            'warehouse',
            'creator',
            'confirmedBy',
            'items.inventoryReservation',
        ])->loadCount('items'));
    }
}
