<?php

namespace App\Http\Controllers;

use App\Actions\SaveDeliveryPreparationAction;
use App\Http\Requests\UpdateDeliveryPreparationRequest;
use App\Models\DeliveryRun;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class UpdateDeliveryPreparationController extends Controller
{
    public function __invoke(
        UpdateDeliveryPreparationRequest $request,
        DeliveryRun $deliveryRun,
        SaveDeliveryPreparationAction $savePreparation,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $savePreparation->handle($deliveryRun, $request->validated('items'), $user);

        return redirect()->route('delivery-runs.show', $deliveryRun)
            ->with('success', 'Cantidades preparadas guardadas.');
    }
}
