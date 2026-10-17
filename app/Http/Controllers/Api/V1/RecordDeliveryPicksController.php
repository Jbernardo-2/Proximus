<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RecordDeliveryPicksAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\RecordDeliveryPicksRequest;
use App\Http\Resources\Api\V1\DeliveryRunResource;
use App\Models\DeliveryRun;
use App\Models\User;

class RecordDeliveryPicksController extends Controller
{
    public function __invoke(RecordDeliveryPicksRequest $request, DeliveryRun $deliveryRun, RecordDeliveryPicksAction $recordPicks): DeliveryRunResource
    {
        /** @var User $user */
        $user = $request->user();
        $run = $recordPicks->handle($deliveryRun, $request->validated('items'), $user);

        return new DeliveryRunResource($run->load([
            'warehouse',
            'driver',
            'vehicle',
            'runOrders' => fn ($orders) => $orders->with(['order', 'items', 'preparedBy', 'payments'])->orderBy('visit_order'),
        ])->loadCount('runOrders'));
    }
}
