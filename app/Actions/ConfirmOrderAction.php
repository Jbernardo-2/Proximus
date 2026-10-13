<?php

namespace App\Actions;

use App\CustomerCreditAuthorizationType;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\OrderStatus;
use App\PaymentTerm;
use App\Services\CustomerCreditService;
use App\Services\OperationalNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmOrderAction
{
    public function __construct(
        private RecalculateOrderTotalsAction $recalculateTotals,
        private ReserveOrderStockAction $reserveStock,
        private CustomerCreditService $customerCredit,
        private OperationalNotificationService $notifications,
    ) {}

    public function handle(Order $order, User $actor, ?string $creditOverrideReason = null): Order
    {
        $confirmedOrder = DB::transaction(function () use ($order, $actor, $creditOverrideReason): Order {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! $lockedOrder->isDraft()) {
                throw ValidationException::withMessages([
                    'order' => ['Solo se puede confirmar un pedido en borrador.'],
                ]);
            }

            if (! $lockedOrder->items()->exists()) {
                throw ValidationException::withMessages([
                    'items' => ['Agrega al menos un producto antes de confirmar el pedido.'],
                ]);
            }

            $this->recalculateTotals->handle($lockedOrder);

            if ($lockedOrder->payment_term === PaymentTerm::Credit) {
                $customer = Customer::query()->lockForUpdate()->findOrFail($lockedOrder->customer_id);
                $this->customerCredit->authorize(
                    customer: $customer,
                    amount: (string) $lockedOrder->total,
                    actor: $actor,
                    type: CustomerCreditAuthorizationType::OrderConfirmation,
                    order: $lockedOrder,
                    overrideReason: $creditOverrideReason,
                );
            }

            $this->reserveStock->handle($lockedOrder, $actor);
            $lockedOrder->forceFill([
                'status' => OrderStatus::Confirmed,
                'confirmed_at' => now(),
                'confirmed_by' => $actor->id,
                'cancelled_at' => null,
                'cancelled_by' => null,
                'cancellation_reason' => null,
            ])->save();
            $lockedOrder->statusHistory()->create([
                'from_status' => OrderStatus::Draft,
                'to_status' => OrderStatus::Confirmed,
                'changed_by' => $actor->id,
                'reason' => 'Pedido confirmado y mercancía comprometida para preparación en bodega.',
            ]);

            return $lockedOrder;
        });

        $this->notifications->orderConfirmed($confirmedOrder);

        return $confirmedOrder;
    }
}
