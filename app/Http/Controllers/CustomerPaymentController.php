<?php

namespace App\Http\Controllers;

use App\Actions\RecordCustomerPaymentAction;
use App\Actions\RecordCustomerVisitAction;
use App\CustomerVisitType;
use App\Http\Requests\StoreCustomerPaymentRequest;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class CustomerPaymentController extends Controller
{
    public function store(
        StoreCustomerPaymentRequest $request,
        Customer $customer,
        RecordCustomerPaymentAction $recordPayment,
        RecordCustomerVisitAction $recordVisit,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        Gate::authorize('view', $customer);
        $payment = $recordPayment->handle($customer, $request->validated(), $user);
        $recordVisit->forRequest($request, CustomerVisitType::AccountPayment, $customer, $payment);

        return redirect()->route('customers.payments.receipt.show', [$customer, $payment])
            ->with('success', "Abono {$payment->receipt_number} registrado correctamente.");
    }
}
