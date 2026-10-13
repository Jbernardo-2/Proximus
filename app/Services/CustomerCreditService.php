<?php

namespace App\Services;

use App\CustomerCreditAuthorizationType;
use App\CustomerCreditStatus;
use App\Models\Customer;
use App\Models\CustomerCreditAuthorization;
use App\Models\CustomerReceivable;
use App\Models\DeliveryRunOrder;
use App\Models\Order;
use App\Models\User;
use App\OrderStatus;
use App\PaymentTerm;
use Illuminate\Validation\ValidationException;

class CustomerCreditService
{
    /**
     * @return array{receivable_balance: string, ledger_balance: string, committed_credit: string, exposure: string, credit_limit: string, available_credit: string, overdue_balance: string, open_receivables: int, overdue_receivables: int, profile_status: CustomerCreditStatus, reconciliation_difference: string}
     */
    public function summary(Customer $customer, ?Order $excludeOrder = null): array
    {
        $profile = $customer->relationLoaded('creditProfile')
            ? $customer->creditProfile
            : $customer->creditProfile()->first();
        $receivables = CustomerReceivable::query()->whereBelongsTo($customer)->outstanding();
        $receivableBalance = (string) (clone $receivables)->sum('outstanding_amount');
        $overdue = (clone $receivables)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today());
        $overdueBalance = (string) (clone $overdue)->sum('outstanding_amount');
        $committedCredit = (string) Order::query()
            ->whereBelongsTo($customer)
            ->where('payment_term', PaymentTerm::Credit->value)
            ->whereIn('status', [
                OrderStatus::Confirmed->value,
                OrderStatus::Assigned->value,
                OrderStatus::Loaded->value,
                OrderStatus::InTransit->value,
            ])
            ->when($excludeOrder !== null, fn ($query) => $query->whereKeyNot($excludeOrder->id))
            ->sum('total');
        $accountTotals = $customer->accountEntries()
            ->selectRaw('coalesce(sum(debit_amount), 0) as debit_total, coalesce(sum(credit_amount), 0) as credit_total')
            ->first();
        $ledgerBalance = bcsub(
            (string) ($accountTotals?->debit_total ?? '0'),
            (string) ($accountTotals?->credit_total ?? '0'),
            4,
        );
        $creditLimit = (string) ($profile?->credit_limit ?? '0');
        $exposure = bcadd($receivableBalance, $committedCredit, 4);

        return [
            'receivable_balance' => $receivableBalance,
            'ledger_balance' => $ledgerBalance,
            'committed_credit' => $committedCredit,
            'exposure' => $exposure,
            'credit_limit' => $creditLimit,
            'available_credit' => bcsub($creditLimit, $exposure, 4),
            'overdue_balance' => $overdueBalance,
            'open_receivables' => (clone $receivables)->count(),
            'overdue_receivables' => (clone $overdue)->count(),
            'profile_status' => $profile?->status ?? CustomerCreditStatus::Disabled,
            'reconciliation_difference' => bcsub($ledgerBalance, $receivableBalance, 4),
        ];
    }

    public function authorize(
        Customer $customer,
        string $amount,
        User $actor,
        CustomerCreditAuthorizationType $type,
        ?Order $order = null,
        ?DeliveryRunOrder $runOrder = null,
        ?string $overrideReason = null,
    ): ?CustomerCreditAuthorization {
        $profile = $customer->creditProfile()->lockForUpdate()->first();

        if ($profile === null || ! $profile->status->allowsNewCredit()) {
            throw ValidationException::withMessages([
                'credit' => ['El cliente no tiene una línea de crédito activa.'],
            ]);
        }

        $summary = $this->summary($customer, $order);
        $exposureAfter = bcadd($summary['exposure'], $amount, 4);

        if (bccomp($exposureAfter, (string) $profile->credit_limit, 4) <= 0) {
            return null;
        }

        $exceededBy = bcsub($exposureAfter, (string) $profile->credit_limit, 4);

        if (! $actor->canOverrideCustomerCredit() || blank($overrideReason)) {
            throw ValidationException::withMessages([
                'credit' => [sprintf(
                    'El crédito excede el disponible en HNL %s. Se requiere autorización de un supervisor con motivo.',
                    number_format(max(0, (float) $summary['available_credit']), 2),
                )],
            ]);
        }

        return CustomerCreditAuthorization::query()->create([
            'customer_id' => $customer->id,
            'type' => $type,
            'order_id' => $order?->id,
            'delivery_run_order_id' => $runOrder?->id,
            'requested_amount' => $amount,
            'exposure_before' => $summary['exposure'],
            'credit_limit' => $profile->credit_limit,
            'exceeded_by' => $exceededBy,
            'reason' => $overrideReason,
            'authorized_by' => $actor->id,
            'authorized_at' => now(),
        ]);
    }
}
