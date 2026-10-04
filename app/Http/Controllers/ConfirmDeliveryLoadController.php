<?php

namespace App\Http\Controllers;

use App\Actions\ConfirmDeliveryLoadAction;
use App\Models\DeliveryRun;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ConfirmDeliveryLoadController extends Controller
{
    public function __invoke(
        Request $request,
        DeliveryRun $deliveryRun,
        ConfirmDeliveryLoadAction $confirmLoad,
    ): RedirectResponse {
        Gate::authorize('load', $deliveryRun);
        /** @var User $user */
        $user = $request->user();
        $confirmLoad->handle($deliveryRun, $user);

        return redirect()->route('delivery-runs.show', $deliveryRun)
            ->with('success', 'Carga confirmada. La mercancía quedó registrada como inventario en tránsito.');
    }
}
