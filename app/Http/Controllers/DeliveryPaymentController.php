<?php

namespace App\Http\Controllers;

use App\Actions\RecordDeliverySettlementAction;
use App\DeliveryOrderStatus;
use App\Http\Requests\StoreDeliverySettlementRequest;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunOrder;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class DeliveryPaymentController extends Controller
{
    public function store(
        StoreDeliverySettlementRequest $request,
        DeliveryRun $deliveryRun,
        DeliveryRunOrder $runOrder,
        RecordDeliverySettlementAction $recordSettlement,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $recordSettlement->handle($deliveryRun, $runOrder, $request->validated(), $user);
        $completedStatuses = collect(DeliveryOrderStatus::cases())
            ->filter(fn (DeliveryOrderStatus $status): bool => $status->isCompleted())
            ->map->value;
        $nextStop = $deliveryRun->runOrders()
            ->whereNotIn('status', $completedStatuses)
            ->where('visit_order', '>', $runOrder->visit_order)
            ->orderBy('visit_order')
            ->orderBy('id')
            ->first()
            ?? $deliveryRun->runOrders()
                ->whereNotIn('status', $completedStatuses)
                ->orderBy('visit_order')
                ->orderBy('id')
                ->first();
        $target = $nextStop === null ? '#delivery-progress' : '#stop-'.$nextStop->id;
        $message = $nextStop === null
            ? 'Cobro registrado. Todas las entregas de la jornada están completas.'
            : "Cobro registrado. Siguiente entrega: parada #{$nextStop->visit_order}.";

        return redirect()->to(route('delivery-runs.show', $deliveryRun).$target)
            ->with('success', $message)
            ->with('delivery_invoice_run_order_id', $runOrder->id);
    }
}
