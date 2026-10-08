<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\SaveDeliveryPreparationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateDeliveryPreparationRequest;
use App\Http\Resources\Api\V1\DeliveryRunResource;
use App\Models\DeliveryRun;
use App\Models\User;

class UpdateDeliveryPreparationController extends Controller
{
    public function __invoke(
        UpdateDeliveryPreparationRequest $request,
        DeliveryRun $deliveryRun,
        SaveDeliveryPreparationAction $savePreparation,
    ): DeliveryRunResource {
        /** @var User $user */
        $user = $request->user();
        $savedRun = $savePreparation->handle($deliveryRun, $request->validated('items'), $user);

        return new DeliveryRunResource($savedRun->load([
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
