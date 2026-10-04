<?php

namespace App\Http\Controllers;

use App\Actions\CancelDeliveryRunAction;
use App\Http\Requests\CancelDeliveryRunRequest;
use App\Models\DeliveryRun;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class CancelDeliveryRunController extends Controller
{
    public function __invoke(
        CancelDeliveryRunRequest $request,
        DeliveryRun $deliveryRun,
        CancelDeliveryRunAction $cancelDeliveryRun,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $cancelDeliveryRun->handle($deliveryRun, $request->string('reason')->toString(), $user);

        return redirect()->route('delivery-runs.show', $deliveryRun)
            ->with('success', 'Jornada cancelada y pedidos devueltos al estado confirmado.');
    }
}
