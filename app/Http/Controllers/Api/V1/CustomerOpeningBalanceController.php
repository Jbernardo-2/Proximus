<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RecordCustomerOpeningBalanceAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerOpeningBalanceRequest;
use App\Http\Resources\Api\V1\CustomerReceivableResource;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class CustomerOpeningBalanceController extends Controller
{
    public function __invoke(
        StoreCustomerOpeningBalanceRequest $request,
        Customer $customer,
        RecordCustomerOpeningBalanceAction $recordOpeningBalance,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return (new CustomerReceivableResource($recordOpeningBalance->handle($customer, $request->validated(), $user)))
            ->response()
            ->setStatusCode(201);
    }
}
