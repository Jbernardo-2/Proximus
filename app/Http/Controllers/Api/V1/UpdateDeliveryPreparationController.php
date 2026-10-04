<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\SaveDeliveryPreparationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateDeliveryPreparationRequest;
use App\Http\Resources\Api\V1\DeliveryRunResource;
use App\Models\DeliveryRun;

class UpdateDeliveryPreparationController extends Controller
{
    public function __invoke(
        UpdateDeliveryPreparationRequest $request,
        DeliveryRun $deliveryRun,
        SaveDeliveryPreparationAction $savePreparation,
    ): DeliveryRunResource {
        $savedRun = $savePreparation->handle($deliveryRun, $request->validated('items'));

        return new DeliveryRunResource($savedRun->load([
            'warehouse',
            'driver',
            'vehicle',
            'runOrders.order',
            'runOrders.items',
            'runOrders.payments',
        ])->loadCount('runOrders'));
    }
}
