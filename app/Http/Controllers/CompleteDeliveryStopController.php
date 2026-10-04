<?php

namespace App\Http\Controllers;

use App\Actions\CompleteDeliveryStopAction;
use App\Http\Requests\CompleteDeliveryStopRequest;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunOrder;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class CompleteDeliveryStopController extends Controller
{
    public function __invoke(
        CompleteDeliveryStopRequest $request,
        DeliveryRun $deliveryRun,
        DeliveryRunOrder $runOrder,
        CompleteDeliveryStopAction $completeStop,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $data = $request->safe()->except('items');
        $completeStop->handle($deliveryRun, $runOrder, $data, $request->validated('items'), $user);

        return redirect()->route('delivery-runs.show', $deliveryRun)
            ->with('success', 'Resultado de la parada registrado correctamente.');
    }
}
