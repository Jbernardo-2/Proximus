<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ConfirmDeliveryLoadAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DeliveryRunResource;
use App\Models\DeliveryRun;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ConfirmDeliveryLoadController extends Controller
{
    public function __invoke(
        Request $request,
        DeliveryRun $deliveryRun,
        ConfirmDeliveryLoadAction $confirmLoad,
    ): DeliveryRunResource {
        Gate::authorize('load', $deliveryRun);
        /** @var User $user */
        $user = $request->user();
        $loadedRun = $confirmLoad->handle($deliveryRun, $user);

        return new DeliveryRunResource($loadedRun->load([
            'warehouse',
            'driver',
            'vehicle',
            'runOrders.order',
            'runOrders.items',
            'runOrders.preparedBy',
            'runOrders.payments',
        ])->loadCount('runOrders'));
    }
}
