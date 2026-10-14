<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CustomerAccountEntryResource;
use App\Http\Resources\Api\V1\CustomerReceivableResource;
use App\Models\Customer;
use App\Models\CustomerReceivable;
use App\Services\CustomerCreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CustomerCreditController extends Controller
{
    public function __invoke(Customer $customer, CustomerCreditService $creditService): JsonResponse
    {
        Gate::authorize('view', $customer);
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
                'open_receivables' => CustomerReceivableResource::collection(
                    CustomerReceivable::query()->whereBelongsTo($customer)->outstanding()->orderBy('due_date')->orderBy('id')->get(),
                )->resolve(),
                'recent_entries' => CustomerAccountEntryResource::collection(
                    $customer->accountEntries()->latest('occurred_at')->orderByDesc('id')->limit(30)->get(),
                )->resolve(),
            ],
        ]);
    }
}
