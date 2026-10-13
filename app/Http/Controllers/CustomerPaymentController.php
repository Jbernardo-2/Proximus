<?php

namespace App\Http\Controllers;

use App\Actions\RecordCustomerPaymentAction;
use App\Http\Requests\StoreCustomerPaymentRequest;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class CustomerPaymentController extends Controller
{
    public function store(
        StoreCustomerPaymentRequest $request,
        Customer $customer,
        RecordCustomerPaymentAction $recordPayment,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $payment = $recordPayment->handle($customer, $request->validated(), $user);

        return redirect()->route('customers.payments.receipt.show', [$customer, $payment])
            ->with('success', "Abono {$payment->receipt_number} registrado correctamente.");
    }
}
