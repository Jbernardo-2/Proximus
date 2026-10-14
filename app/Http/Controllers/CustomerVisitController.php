<?php

namespace App\Http\Controllers;

use App\Actions\RecordCustomerVisitAction;
use App\CustomerVisitReason;
use App\CustomerVisitType;
use App\Http\Requests\StoreCustomerVisitRequest;
use App\Models\Customer;
use App\Models\User;
use App\Services\VisitLocation;
use Illuminate\Http\RedirectResponse;

class CustomerVisitController extends Controller
{
    public function store(
        StoreCustomerVisitRequest $request,
        Customer $customer,
        RecordCustomerVisitAction $recordVisit,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $recordVisit->handle(
            actor: $user,
            customer: $customer,
            type: CustomerVisitType::NoSale,
            location: VisitLocation::fromRequest($request),
            reason: CustomerVisitReason::from($request->validated('reason')),
            notes: $request->validated('notes'),
            clientReference: $request->validated('client_reference'),
        );

        return redirect()->route('customers.show', $customer)
            ->with('success', 'Visita sin pedido registrada.');
    }
}
