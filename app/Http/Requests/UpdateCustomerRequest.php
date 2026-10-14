<?php

namespace App\Http\Requests;

use App\Models\Customer;

class UpdateCustomerRequest extends StoreCustomerRequest
{
    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $customer instanceof Customer
            && ($this->user()?->can('update', $customer) ?? false);
    }
}
