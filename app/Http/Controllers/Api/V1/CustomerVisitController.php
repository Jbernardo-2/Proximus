<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RecordCustomerVisitAction;
use App\CustomerVisitReason;
use App\CustomerVisitType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerVisitRequest;
use App\Models\Customer;
use App\Models\User;
use App\Services\VisitLocation;
use Illuminate\Http\JsonResponse;

class CustomerVisitController extends Controller
{
    public function store(
        StoreCustomerVisitRequest $request,
        Customer $customer,
        RecordCustomerVisitAction $recordVisit,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $visit = $recordVisit->handle(
            actor: $user,
            customer: $customer,
            type: CustomerVisitType::NoSale,
            location: VisitLocation::fromRequest($request),
            reason: CustomerVisitReason::from($request->validated('reason')),
            notes: $request->validated('notes'),
            clientReference: $request->validated('client_reference'),
        );

        return response()->json([
            'data' => [
                'id' => $visit->id,
                'client_reference' => $visit->client_reference,
                'customer_id' => $visit->customer_id,
                'type' => $visit->type->value,
                'reason' => $visit->reason?->value,
                'notes' => $visit->notes,
                'location_status' => $visit->location_status->value,
                'latitude' => $visit->latitude,
                'longitude' => $visit->longitude,
                'accuracy_meters' => $visit->accuracy_meters,
                'distance_meters' => $visit->distance_meters,
                'occurred_at' => $visit->occurred_at->toIso8601String(),
            ],
        ], $visit->wasRecentlyCreated ? 201 : 200);
    }
}
