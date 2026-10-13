<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ConfirmOrderAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmOrderRequest;
use App\Http\Resources\Api\V1\OrderResource;
use App\Models\Order;
use App\Models\User;

class ConfirmOrderController extends Controller
{
    public function __invoke(
        ConfirmOrderRequest $request,
        Order $order,
        ConfirmOrderAction $confirmOrder,
    ): OrderResource {
        /** @var User $user */
        $user = $request->user();
        $confirmedOrder = $confirmOrder->handle(
            $order,
            $user,
            $request->validated('credit_override_reason'),
        );

        return new OrderResource($confirmedOrder->load([
            'warehouse',
            'creator',
            'confirmedBy',
            'items.inventoryReservation',
        ])->loadCount('items'));
    }
}
