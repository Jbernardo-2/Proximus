<?php

namespace App\Services;

use App\CustomerCreditAuthorizationType;
use App\CustomerCreditStatus;
use App\Models\Customer;
use App\Models\CustomerCreditAuthorization;
use App\Models\CustomerCreditProfile;
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

        if (app(OfflineSync::class)->isActive()) {
            return $this->recordOfflineException($customer, $profile, $amount, $actor, $type, $order, $runOrder);
        }

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

    /**
     * Credit granted on a device without connection is a fact already agreed with the customer:
     * it is kept, and anything beyond what the customer had available is left for the supervisor.
     */
    private function recordOfflineException(
        Customer $customer,
        ?CustomerCreditProfile $profile,
        string $amount,
        User $actor,
        CustomerCreditAuthorizationType $type,
        ?Order $order,
        ?DeliveryRunOrder $runOrder,
    ): ?CustomerCreditAuthorization {
        $summary = $this->summary($customer, $order);
        $creditLimit = (string) ($profile?->credit_limit ?? '0');
        $exposureAfter = bcadd($summary['exposure'], $amount, 4);
        $allowsCredit = $profile?->status->allowsNewCredit() ?? false;

        if ($allowsCredit && bccomp($exposureAfter, $creditLimit, 4) <= 0) {
            return null;
        }

        $exceededBy = bcsub($exposureAfter, $creditLimit, 4);

        return CustomerCreditAuthorization::query()->create([
            'customer_id' => $customer->id,
            'type' => $type,
            'order_id' => $order?->id,
            'delivery_run_order_id' => $runOrder?->id,
            'requested_amount' => $amount,
            'exposure_before' => $summary['exposure'],
            'credit_limit' => $creditLimit,
            'exceeded_by' => bccomp($exceededBy, '0', 4) > 0 ? $exceededBy : '0.0000',
            'reason' => $allowsCredit
                ? 'Crédito registrado sin conexión por encima del disponible.'
                : 'Crédito registrado sin conexión a un cliente sin línea de crédito activa.',
            'authorized_by' => $actor->id,
            'authorized_at' => now(),
        ]);
    }
}
