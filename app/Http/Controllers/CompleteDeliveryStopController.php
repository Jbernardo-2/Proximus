<?php

namespace App\Http\Controllers;

use App\Actions\CompleteDeliveryStopAction;
use App\DeliveryOrderStatus;
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
        $result = $completeStop->handle($deliveryRun, $runOrder, $data, $request->validated('items'), $user);

        if ($result->status === DeliveryOrderStatus::AwaitingPayment) {
            return redirect()->to(route('delivery-runs.show', $deliveryRun).'#stop-'.$result->id)
                ->with('success', 'Resultado guardado. Distribuye ahora el total entre los métodos de pago y/o crédito.');
        }

        return redirect()->route('delivery-runs.show', $deliveryRun)
            ->with('success', 'Resultado de la parada registrado correctamente.');
    }
}
