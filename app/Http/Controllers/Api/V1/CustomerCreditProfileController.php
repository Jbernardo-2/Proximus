<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\UpdateCustomerCreditProfileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCustomerCreditProfileRequest;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class CustomerCreditProfileController extends Controller
{
    public function __invoke(
        UpdateCustomerCreditProfileRequest $request,
        Customer $customer,
        UpdateCustomerCreditProfileAction $updateProfile,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $profile = $updateProfile->handle($customer, $request->validated(), $user);

        return response()->json(['data' => [
            'customer_id' => $customer->id,
            'status' => $profile->status->value,
            'status_label' => $profile->status->label(),
            'credit_limit' => $profile->credit_limit,
            'term_days' => $profile->term_days,
            'grace_days' => $profile->grace_days,
        ]]);
    }
}
