<?php

namespace App\Actions;

use App\DeliveryOrderStatus;
use App\DeliveryPaymentStatus;
use App\Models\DeliveryRun;
use App\PaymentMethod;
use App\Services\DeliveryRunAccountPayments;
use App\SettlementMethod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefreshDeliveryFinancialsAction
{
    public function __construct(private DeliveryRunAccountPayments $accountPayments) {}

    public function handle(DeliveryRun $deliveryRun): DeliveryRun
    {
        return DB::transaction(function () use ($deliveryRun): DeliveryRun {
            $run = DeliveryRun::query()
                ->with(['runOrders.order', 'runOrders.payments', 'runOrders.settlement.lines'])
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

                $settlementCredit = $runOrder->settlement?->lines
                    ->where('method', SettlementMethod::Credit)
                    ->reduce(
                        fn (string $total, $line): string => bcadd($total, (string) $line->amount, 4),
                        '0.0000',
                    );
                $balanceDue = $runOrder->settlement === null
                    ? bcsub((string) $runOrder->delivered_total, $orderCollected, 4)
                    : ($settlementCredit ?? '0.0000');
                $runOrder->forceFill([
                    'collected_total' => $orderCollected,
                    'balance_due' => $balanceDue,
                ])->save();
                $deliveredTotal = bcadd($deliveredTotal, (string) $runOrder->delivered_total, 4);
                $collectedTotal = bcadd($collectedTotal, $orderCollected, 4);
                if ($runOrder->settlement !== null || $runOrder->status !== DeliveryOrderStatus::AwaitingPayment) {
                    $creditTotal = bcadd($creditTotal, $balanceDue, 4);
                }

                foreach ($activePayments as $payment) {
                    $methodTotals[$payment->method->value] = bcadd(
                        $methodTotals[$payment->method->value],
                        (string) $payment->amount,
                        4,
                    );
                }
            }

            // The driver hands over the cash of old debts collected on the way too.
            $accountPayments = $this->accountPayments->byMethod($this->accountPayments->for($run));
            $accountPaymentCash = bcadd($accountPayments[PaymentMethod::Cash->value] ?? '0', '0', 4);
            $accountPaymentTotal = array_reduce($accountPayments, fn (string $sum, string $amount): string => bcadd($sum, $amount, 4), '0.0000');
            $cashExpected = bcadd($methodTotals[PaymentMethod::Cash->value], $accountPaymentCash, 4);
            $cashDifference = $run->cash_declared === null
                ? '0.0000'
                : bcsub((string) $run->cash_declared, $cashExpected, 4);
            $run->forceFill([
                'delivered_total' => $deliveredTotal,
                'collected_total' => $collectedTotal,
                'cash_expected' => $cashExpected,
                'account_payment_cash' => $accountPaymentCash,
                'account_payment_total' => $accountPaymentTotal,
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
