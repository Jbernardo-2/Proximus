<?php

namespace App\Policies;

use App\CustomerAccountEntryType;
use App\Models\CustomerAccountEntry;
use App\Models\User;

class CustomerAccountEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canViewCustomerCredit();
    }

    public function view(User $user, CustomerAccountEntry $customerAccountEntry): bool
    {
        return $user->canViewCustomerCredit()
            && $customerAccountEntry->type === CustomerAccountEntryType::CustomerPayment;
    }
}
