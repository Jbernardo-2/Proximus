<?php

namespace App\Http\Controllers;

use App\Actions\ReopenDeliveryStopAction;
use App\Http\Requests\ReopenDeliveryStopRequest;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunOrder;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class ReopenDeliveryStopController extends Controller
{
    public function __invoke(
        ReopenDeliveryStopRequest $request,
        DeliveryRun $deliveryRun,
        DeliveryRunOrder $runOrder,
        ReopenDeliveryStopAction $reopenStop,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $reopened = $reopenStop->handle($deliveryRun, $runOrder, $user);

        return redirect()->to(route('delivery-runs.show', $deliveryRun).'#stop-'.$reopened->id)
            ->with('success', 'Parada reabierta. Corrige las cantidades y guarda de nuevo.');
    }
}
