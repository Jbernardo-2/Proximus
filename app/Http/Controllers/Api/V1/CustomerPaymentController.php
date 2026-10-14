<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RecordCustomerPaymentAction;
use App\Actions\RecordCustomerVisitAction;
use App\CustomerVisitType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerPaymentRequest;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class CustomerPaymentController extends Controller
{
    public function store(
        StoreCustomerPaymentRequest $request,
        Customer $customer,
        RecordCustomerPaymentAction $recordPayment,
        RecordCustomerVisitAction $recordVisit,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $payment = $recordPayment->handle($customer, $request->validated(), $user);
        $recordVisit->forRequest($request, CustomerVisitType::AccountPayment, $customer, $payment);

        return response()->json([
            'data' => [
                'id' => $payment->id,
                'receipt_number' => $payment->receipt_number,
                'amount' => $payment->credit_amount,
                'payment_lines' => $payment->paymentLines->map(fn ($line): array => [
                    'method' => $line->method->value,
                    'amount' => $line->amount,
                    'reference' => $line->reference,
                ])->values(),
            ],
        ], 201);
    }
}
