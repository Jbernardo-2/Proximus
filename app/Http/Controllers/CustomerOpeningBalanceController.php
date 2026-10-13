<?php

namespace App\Http\Controllers;

use App\Actions\RecordCustomerOpeningBalanceAction;
use App\Http\Requests\StoreCustomerOpeningBalanceRequest;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class CustomerOpeningBalanceController extends Controller
{
    public function __invoke(
        StoreCustomerOpeningBalanceRequest $request,
        Customer $customer,
        RecordCustomerOpeningBalanceAction $recordOpeningBalance,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $receivable = $recordOpeningBalance->handle($customer, $request->validated(), $user);

        return redirect()->to(route('customers.show', $customer).'#account')
            ->with('success', "Saldo inicial {$receivable->document_number} registrado correctamente.");
    }
}
