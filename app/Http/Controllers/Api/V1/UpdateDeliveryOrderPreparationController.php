<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\SaveDeliveryOrderPreparationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateDeliveryOrderPreparationRequest;
use App\Http\Resources\Api\V1\DeliveryRunOrderResource;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunOrder;
use App\Models\User;

class UpdateDeliveryOrderPreparationController extends Controller
{
    public function __invoke(
        UpdateDeliveryOrderPreparationRequest $request,
        DeliveryRun $deliveryRun,
        DeliveryRunOrder $runOrder,
        SaveDeliveryOrderPreparationAction $savePreparation,
    ): DeliveryRunOrderResource {
        /** @var User $user */
        $user = $request->user();
        $prepared = $savePreparation->handle(
            $deliveryRun,
            $runOrder,
            $request->validated('items'),
            $user,
        );

        return new DeliveryRunOrderResource($prepared->load(['order', 'items', 'preparedBy']));
    }
}
