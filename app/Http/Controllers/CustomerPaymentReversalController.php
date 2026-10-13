<?php

namespace App\Http\Controllers;

use App\Actions\ReverseCustomerPaymentAction;
use App\Http\Requests\ReverseCustomerPaymentRequest;
use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class CustomerPaymentReversalController extends Controller
{
    public function __invoke(
        ReverseCustomerPaymentRequest $request,
        Customer $customer,
        CustomerAccountEntry $payment,
        ReverseCustomerPaymentAction $reversePayment,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $reversal = $reversePayment->handle($customer, $payment, $request->string('reason')->toString(), $user);

        return redirect()->to(route('customers.show', $customer).'#account')
            ->with('success', "Abono revertido mediante {$reversal->receipt_number}.");
    }
}
