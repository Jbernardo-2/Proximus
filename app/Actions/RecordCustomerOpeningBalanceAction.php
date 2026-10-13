<?php

namespace App\Actions;

use App\CustomerAccountEntryStatus;
use App\CustomerAccountEntryType;
use App\CustomerReceivableSource;
use App\CustomerReceivableStatus;
use App\Models\Customer;
use App\Models\CustomerReceivable;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RecordCustomerOpeningBalanceAction
{
    public function __construct(private GenerateDeliveryNumberAction $generateNumber) {}

    /** @param array{amount: mixed, document_number?: ?string, issued_at: string, due_date: string, notes?: ?string} $data */
    public function handle(Customer $customer, array $data, User $actor): CustomerReceivable
    {
        return DB::transaction(function () use ($customer, $data, $actor): CustomerReceivable {
            $lockedCustomer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            $openedAt = now();
            $documentNumber = $data['document_number']
                ?? $this->generateNumber->handle('opening_balance', 'SAL', $openedAt);
            $receivable = $lockedCustomer->receivables()->create([
                'source' => CustomerReceivableSource::OpeningBalance,
                'order_id' => null,
                'delivery_run_order_id' => null,
                'document_number' => $documentNumber,
                'original_amount' => $data['amount'],
                'outstanding_amount' => $data['amount'],
                'currency' => config('proximus.currency', 'HNL'),
                'status' => CustomerReceivableStatus::Open,
                'opened_at' => $openedAt,
                'issued_at' => $data['issued_at'],
                'due_date' => $data['due_date'],
                'term_days' => null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            $lockedCustomer->accountEntries()->create([
                'type' => CustomerAccountEntryType::OpeningBalance,
                'status' => CustomerAccountEntryStatus::Active,
                'receipt_number' => $documentNumber,
                'debit_amount' => $data['amount'],
                'credit_amount' => '0.0000',
                'currency' => config('proximus.currency', 'HNL'),
                'notes' => $data['notes'] ?? 'Saldo inicial importado del control anterior.',
                'occurred_at' => $openedAt,
                'created_by' => $actor->id,
            ]);

            return $receivable;
        }, 3);
    }
}
