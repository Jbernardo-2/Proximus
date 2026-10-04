<?php

namespace App\Actions;

use App\DeliveryPaymentStatus;
use App\Models\DeliveryRun;
use App\PaymentMethod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefreshDeliveryFinancialsAction
{
    public function handle(DeliveryRun $deliveryRun): DeliveryRun
    {
        return DB::transaction(function () use ($deliveryRun): DeliveryRun {
            $run = DeliveryRun::query()
                ->with(['runOrders.order', 'runOrders.payments'])
                ->lockForUpdate()
                ->findOrFail($deliveryRun->id);
            $deliveredTotal = '0.0000';
            $collectedTotal = '0.0000';
            $creditTotal = '0.0000';
            $methodTotals = collect(PaymentMethod::cases())
                ->mapWithKeys(fn (PaymentMethod $method): array => [$method->value => '0.0000'])
                ->all();

            foreach ($run->runOrders as $runOrder) {
                $activePayments = $runOrder->payments->where('status', DeliveryPaymentStatus::Active);
                $orderCollected = $activePayments->reduce(
                    fn (string $total, $payment): string => bcadd($total, (string) $payment->amount, 4),
                    '0.0000',
                );

                if (bccomp($orderCollected, (string) $runOrder->delivered_total, 4) > 0) {
                    throw ValidationException::withMessages([
                        'amount' => ["Los cobros del pedido {$runOrder->order->order_number} superan el total entregado."],
                    ]);
                }

                $balanceDue = bcsub((string) $runOrder->delivered_total, $orderCollected, 4);
                $runOrder->forceFill([
                    'collected_total' => $orderCollected,
                    'balance_due' => $balanceDue,
                ])->save();
                $deliveredTotal = bcadd($deliveredTotal, (string) $runOrder->delivered_total, 4);
                $collectedTotal = bcadd($collectedTotal, $orderCollected, 4);
                $creditTotal = bcadd($creditTotal, $balanceDue, 4);

                foreach ($activePayments as $payment) {
                    $methodTotals[$payment->method->value] = bcadd(
                        $methodTotals[$payment->method->value],
                        (string) $payment->amount,
                        4,
                    );
                }
            }

            $cashDifference = $run->cash_declared === null
                ? '0.0000'
                : bcsub((string) $run->cash_declared, $methodTotals[PaymentMethod::Cash->value], 4);
            $run->forceFill([
                'delivered_total' => $deliveredTotal,
                'collected_total' => $collectedTotal,
                'cash_expected' => $methodTotals[PaymentMethod::Cash->value],
                'cash_difference' => $cashDifference,
                'transfer_total' => $methodTotals[PaymentMethod::BankTransfer->value],
                'card_total' => $methodTotals[PaymentMethod::Card->value],
                'check_total' => $methodTotals[PaymentMethod::Check->value],
                'other_payment_total' => $methodTotals[PaymentMethod::Other->value],
                'credit_total' => $creditTotal,
            ])->save();

            return $run->refresh();
        });
    }
}
