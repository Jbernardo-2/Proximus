<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\StartDeliveryPreparationAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DeliveryRunResource;
use App\Models\DeliveryRun;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class StartDeliveryPreparationController extends Controller
{
    public function __invoke(
        Request $request,
        DeliveryRun $deliveryRun,
        StartDeliveryPreparationAction $startPreparation,
    ): DeliveryRunResource {
        Gate::authorize('startPreparation', $deliveryRun);
        /** @var User $user */
        $user = $request->user();

        return new DeliveryRunResource($startPreparation->handle($deliveryRun, $user)->load([
            'warehouse', 'driver', 'vehicle',
        ])->loadCount('runOrders'));
    }
}
