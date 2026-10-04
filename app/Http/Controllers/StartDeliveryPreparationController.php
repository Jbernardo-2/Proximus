<?php

namespace App\Http\Controllers;

use App\Actions\StartDeliveryPreparationAction;
use App\Models\DeliveryRun;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class StartDeliveryPreparationController extends Controller
{
    public function __invoke(
        Request $request,
        DeliveryRun $deliveryRun,
        StartDeliveryPreparationAction $startPreparation,
    ): RedirectResponse {
        Gate::authorize('startPreparation', $deliveryRun);
        /** @var User $user */
        $user = $request->user();
        $startPreparation->handle($deliveryRun, $user);

        return redirect()->route('delivery-runs.show', $deliveryRun)
            ->with('success', 'Preparación iniciada. Registra lo que bodega colocará en la carga.');
    }
}
