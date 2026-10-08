<?php

namespace App\Http\Controllers;

use App\Actions\SaveDeliveryOrderPreparationAction;
use App\Http\Requests\UpdateDeliveryOrderPreparationRequest;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunOrder;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DeliveryRunOrderPreparationController extends Controller
{
    public function edit(DeliveryRun $deliveryRun, DeliveryRunOrder $runOrder): View
    {
        Gate::authorize('prepare', $deliveryRun);
        abort_unless($runOrder->delivery_run_id === $deliveryRun->id, 404);
        $deliveryRun->load(['warehouse', 'driver']);
        $runOrder->load([
            'order.customer',
            'preparedBy',
            'items' => fn ($query) => $query->with(['product', 'presentation'])->orderBy('product_name')->orderBy('id'),
        ]);

        return view('deliveries.preparation', compact('deliveryRun', 'runOrder'));
    }

    public function update(
        UpdateDeliveryOrderPreparationRequest $request,
        DeliveryRun $deliveryRun,
        DeliveryRunOrder $runOrder,
        SaveDeliveryOrderPreparationAction $savePreparation,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $savePreparation->handle($deliveryRun, $runOrder, $request->validated('items'), $user);

        return redirect()->to(route('delivery-runs.show', $deliveryRun).'#preparation-queue')
            ->with('success', "Pedido {$runOrder->order()->value('order_number')} preparado. Continúa con el siguiente pendiente.");
    }
}
