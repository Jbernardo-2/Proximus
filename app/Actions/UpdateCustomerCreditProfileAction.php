<?php

namespace App\Actions;

use App\CustomerCreditStatus;
use App\Models\Customer;
use App\Models\CustomerCreditProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateCustomerCreditProfileAction
{
    /** @param array{status: string, credit_limit: mixed, term_days: int, grace_days: int, notes?: ?string, reason: string} $data */
    public function handle(Customer $customer, array $data, User $actor): CustomerCreditProfile
    {
        return DB::transaction(function () use ($customer, $data, $actor): CustomerCreditProfile {
            $lockedCustomer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            $profile = $lockedCustomer->creditProfile()->lockForUpdate()->first();
            $status = CustomerCreditStatus::from($data['status']);
            $now = now();

            $lockedCustomer->creditProfileHistory()->create([
                'from_status' => $profile?->status,
                'to_status' => $status,
                'old_credit_limit' => $profile?->credit_limit,
                'new_credit_limit' => $data['credit_limit'],
                'old_term_days' => $profile?->term_days,
                'new_term_days' => $data['term_days'],
                'old_grace_days' => $profile?->grace_days,
                'new_grace_days' => $data['grace_days'],
                'reason' => $data['reason'],
                'changed_by' => $actor->id,
                'created_at' => $now,
            ]);

            return $lockedCustomer->creditProfile()->updateOrCreate(
                ['customer_id' => $lockedCustomer->id],
                [
                    'status' => $status,
                    'credit_limit' => $data['credit_limit'],
                    'term_days' => $data['term_days'],
                    'grace_days' => $data['grace_days'],
                    'notes' => $data['notes'] ?? null,
                    'approved_at' => $status === CustomerCreditStatus::Active
                        ? ($profile?->approved_at ?? $now)
                        : $profile?->approved_at,
                    'approved_by' => $status === CustomerCreditStatus::Active
                        ? ($profile?->approved_by ?? $actor->id)
                        : $profile?->approved_by,
                    'updated_by' => $actor->id,
                ],
            );
        }, 3);
    }
}
