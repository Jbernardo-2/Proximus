<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ReverseCustomerPaymentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReverseCustomerPaymentRequest;
use App\Http\Resources\Api\V1\CustomerAccountEntryResource;
use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class CustomerPaymentReversalController extends Controller
{
    public function __invoke(
        ReverseCustomerPaymentRequest $request,
        Customer $customer,
        CustomerAccountEntry $payment,
        ReverseCustomerPaymentAction $reversePayment,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $reversal = $reversePayment->handle($customer, $payment, $request->string('reason')->toString(), $user);

        return (new CustomerAccountEntryResource($reversal))->response()->setStatusCode(201);
    }
}
