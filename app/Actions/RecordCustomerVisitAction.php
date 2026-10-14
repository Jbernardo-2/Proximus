<?php

namespace App\Actions;

use App\CustomerVisitReason;
use App\CustomerVisitType;
use App\Models\Customer;
use App\Models\CustomerVisit;
use App\Models\User;
use App\Services\VisitLocation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;

class RecordCustomerVisitAction
{
    /** Records the visit behind an operation the request just completed, once per operation. */
    public function forRequest(
        Request $request,
        CustomerVisitType $type,
        Customer|string $customer,
        Model $subject,
    ): CustomerVisit {
        /** @var User $actor */
        $actor = $request->user();

        return $this->handle(
            actor: $actor,
            customer: $customer,
            type: $type,
            location: VisitLocation::fromRequest($request),
            subject: $subject,
            clientReference: $type->value.':'.$subject->getKey(),
        );
    }

    public function handle(
        User $actor,
        Customer|string $customer,
        CustomerVisitType $type,
        VisitLocation $location,
        ?Model $subject = null,
        ?CustomerVisitReason $reason = null,
        ?string $notes = null,
        ?string $clientReference = null,
    ): CustomerVisit {
        $customer = $customer instanceof Customer
            ? $customer
            : Customer::query()->withTrashed()->findOrFail($customer);

        if ($clientReference !== null) {
            $existing = CustomerVisit::query()->where('client_reference', $clientReference)->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        try {
            return CustomerVisit::query()->create([
                'client_reference' => $clientReference,
                'customer_id' => $customer->id,
                'user_id' => $actor->id,
                'type' => $type,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'reason' => $reason,
                'notes' => $notes,
                'location_status' => $location->status,
                'latitude' => $location->latitude,
                'longitude' => $location->longitude,
                'accuracy_meters' => $location->accuracyMeters,
                'distance_meters' => $location->distanceTo($customer->latitude, $customer->longitude),
                'occurred_at' => $location->occurredAt,
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            return CustomerVisit::query()->where('client_reference', $clientReference)->first() ?? throw $exception;
        }
    }
}
