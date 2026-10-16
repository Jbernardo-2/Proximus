<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\DepartDeliveryRunAction;
use App\DeliveryRunStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DeliveryRunResource;
use App\Models\DeliveryRun;
use App\Models\User;
use App\Services\OfflineSync;
use App\Services\VisitLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DepartDeliveryRunController extends Controller
{
    public function __invoke(
        Request $request,
        DeliveryRun $deliveryRun,
        DepartDeliveryRunAction $departDeliveryRun,
        OfflineSync $offline,
    ): DeliveryRunResource {
        /** @var User $user */
        $user = $request->user();

        // A departure recorded on the phone that arrives after the run already left
        // (marked on the web, or sent twice) is a fact already reflected: accept it as is.
        $alreadyLeft = $offline->isActive()
            && in_array($deliveryRun->status, [DeliveryRunStatus::InTransit, DeliveryRunStatus::AwaitingSettlement], true);

        if ($alreadyLeft) {
            Gate::authorize('execute', $deliveryRun);
            $departedRun = $deliveryRun;
        } else {
            Gate::authorize('depart', $deliveryRun);
            $departedRun = $departDeliveryRun->handle($deliveryRun, $user, VisitLocation::fromRequest($request)->occurredAt);
        }

        return new DeliveryRunResource($departedRun->load([
            'warehouse', 'driver', 'vehicle', 'runOrders.order', 'runOrders.items', 'runOrders.preparedBy', 'runOrders.payments',
        ])->loadCount('runOrders'));
    }
}
