<?php

namespace App\Actions;

use App\CustomerAccountEntryStatus;
use App\CustomerAccountEntryType;
use App\CustomerReceivableStatus;
use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReverseCustomerPaymentAction
{
    public function __construct(private GenerateDeliveryNumberAction $generateNumber) {}

    public function handle(
        Customer $customer,
        CustomerAccountEntry $payment,
        string $reason,
        User $actor,
    ): CustomerAccountEntry {
        return DB::transaction(function () use ($customer, $payment, $reason, $actor): CustomerAccountEntry {
            Customer::query()->lockForUpdate()->findOrFail($customer->id);
            $lockedPayment = CustomerAccountEntry::query()
                ->where('customer_id', $customer->id)
                ->with(['allocations.receivable', 'paymentLines'])
                ->lockForUpdate()
                ->findOrFail($payment->id);

            if ($lockedPayment->type !== CustomerAccountEntryType::CustomerPayment
                || $lockedPayment->status !== CustomerAccountEntryStatus::Active) {
                throw ValidationException::withMessages([
                    'payment' => ['El movimiento no es un abono activo que pueda revertirse.'],
                ]);
            }

            if ($lockedPayment->reversed_at !== null || $lockedPayment->reversalOf()->exists()) {
                throw ValidationException::withMessages([
                    'payment' => ['El abono ya fue revertido o es una reversión.'],
                ]);
            }

            foreach ($lockedPayment->allocations as $allocation) {
                $receivable = $allocation->receivable;

                if ($receivable->status === CustomerReceivableStatus::Voided) {
                    throw ValidationException::withMessages([
                        'payment' => ['No se puede revertir porque una deuda relacionada fue anulada.'],
                    ]);
                }

                $outstanding = bcadd((string) $receivable->outstanding_amount, (string) $allocation->amount, 4);

                if (bccomp($outstanding, (string) $receivable->original_amount, 4) > 0) {
                    throw ValidationException::withMessages([
                        'payment' => ['La reversión excedería el valor original de una deuda.'],
                    ]);
                }

                $receivable->forceFill([
                    'outstanding_amount' => $outstanding,
                    'status' => bccomp($outstanding, (string) $receivable->original_amount, 4) === 0
                        ? CustomerReceivableStatus::Open
                        : CustomerReceivableStatus::PartiallyPaid,
                    'paid_at' => null,
                ])->save();
            }

            $reversedAt = now();
            $lockedPayment->forceFill([
                'status' => CustomerAccountEntryStatus::Reversed,
                'reversed_at' => $reversedAt,
                'reversed_by' => $actor->id,
            ])->save();

            return CustomerAccountEntry::query()->create([
                'customer_id' => $customer->id,
                'type' => CustomerAccountEntryType::Reversal,
                'status' => CustomerAccountEntryStatus::Active,
                'receipt_number' => $this->generateNumber->handle('customer_reversal', 'REV', $reversedAt),
                'debit_amount' => $lockedPayment->credit_amount,
                'credit_amount' => '0.0000',
                'currency' => $lockedPayment->currency,
                'notes' => $reason,
                'occurred_at' => $reversedAt,
                'created_by' => $actor->id,
                'reversal_of_id' => $lockedPayment->id,
            ]);
        }, 3);
    }
}
