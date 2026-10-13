<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\CustomerCreditService;
use Illuminate\Http\JsonResponse;

class CustomerCreditController extends Controller
{
    public function __invoke(Customer $customer, CustomerCreditService $creditService): JsonResponse
    {
        $customer->load('creditProfile');

        return response()->json([
            'data' => [
                'customer_id' => $customer->id,
                'profile' => $customer->creditProfile === null ? null : [
                    'status' => $customer->creditProfile->status->value,
                    'status_label' => $customer->creditProfile->status->label(),
                    'credit_limit' => $customer->creditProfile->credit_limit,
                    'term_days' => $customer->creditProfile->term_days,
                    'grace_days' => $customer->creditProfile->grace_days,
                ],
                'summary' => $creditService->summary($customer),
            ],
        ]);
    }
}
