<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\DepartDeliveryRunAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DeliveryRunResource;
use App\Models\DeliveryRun;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DepartDeliveryRunController extends Controller
{
    public function __invoke(
        Request $request,
        DeliveryRun $deliveryRun,
        DepartDeliveryRunAction $departDeliveryRun,
    ): DeliveryRunResource {
        Gate::authorize('depart', $deliveryRun);
        /** @var User $user */
        $user = $request->user();
        $departedRun = $departDeliveryRun->handle($deliveryRun, $user);

        return new DeliveryRunResource($departedRun->load([
            'warehouse', 'driver', 'vehicle', 'runOrders.order', 'runOrders.items', 'runOrders.payments',
        ])->loadCount('runOrders'));
    }
}
