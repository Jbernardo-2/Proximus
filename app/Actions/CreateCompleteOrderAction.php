<?php

namespace App\Actions;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Creates an order with its lines and, when asked, confirms it, all or nothing.
 * This is how a device sends an order it took without connection.
 */
class CreateCompleteOrderAction
{
    public function __construct(
        private CreateOrderAction $createOrder,
        private SaveOrderItemAction $saveItem,
        private ConfirmOrderAction $confirmOrder,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data, User $actor): Order
    {
        return DB::transaction(function () use ($data, $actor): Order {
            $order = $this->createOrder->handle($data, $actor);
            $mayOverridePrice = Gate::forUser($actor)->allows('overridePrice', $order);

            foreach ($data['items'] ?? [] as $item) {
                $this->saveItem->handle($order, $item, $actor, $mayOverridePrice);
            }

            if ($data['confirm'] ?? false) {
                Gate::forUser($actor)->authorize('confirm', $order->refresh());
                $this->confirmOrder->handle($order, $actor, $data['credit_override_reason'] ?? null);
            }

            return $order->refresh();
        });
    }
}
