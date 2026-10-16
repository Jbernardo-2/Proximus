<?php

namespace App\Http\Controllers;

use App\Actions\SettleDeliveryRunAction;
use App\Http\Requests\SettleDeliveryRunRequest;
use App\Models\DeliveryRun;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class SettleDeliveryRunController extends Controller
{
    public function __invoke(
        SettleDeliveryRunRequest $request,
        DeliveryRun $deliveryRun,
        SettleDeliveryRunAction $settleDeliveryRun,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $settleDeliveryRun->handle($deliveryRun, $request->validated(), $user);

        // Back to the board: the index shows the result with a link to the detail.
        return redirect()->route('delivery-runs.index')
            ->with('settled_run_id', $deliveryRun->id);
    }
}
