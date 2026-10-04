<?php

namespace App\Http\Controllers;

use App\Actions\DepartDeliveryRunAction;
use App\Models\DeliveryRun;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DepartDeliveryRunController extends Controller
{
    public function __invoke(
        Request $request,
        DeliveryRun $deliveryRun,
        DepartDeliveryRunAction $departDeliveryRun,
    ): RedirectResponse {
        Gate::authorize('depart', $deliveryRun);
        /** @var User $user */
        $user = $request->user();
        $departDeliveryRun->handle($deliveryRun, $user);

        return redirect()->route('delivery-runs.show', $deliveryRun)
            ->with('success', 'Jornada iniciada. Ya puedes registrar entregas y cobros.');
    }
}
