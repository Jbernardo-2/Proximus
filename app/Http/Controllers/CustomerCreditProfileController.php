<?php

namespace App\Http\Controllers;

use App\Actions\UpdateCustomerCreditProfileAction;
use App\Http\Requests\UpdateCustomerCreditProfileRequest;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class CustomerCreditProfileController extends Controller
{
    public function __invoke(
        UpdateCustomerCreditProfileRequest $request,
        Customer $customer,
        UpdateCustomerCreditProfileAction $updateProfile,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $updateProfile->handle($customer, $request->validated(), $user);

        return redirect()->to(route('customers.show', $customer).'#credit')
            ->with('success', 'Política de crédito actualizada correctamente.');
    }
}
