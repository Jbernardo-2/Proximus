<?php

namespace App\Actions;

use App\DeliveryOrderStatus;
use App\DeliveryRunStatus;
use App\InventoryReservationStatus;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunOrder;
use App\Models\Order;
use App\Models\User;
use App\OrderStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignOrderToDeliveryRunAction
{
    public function handle(DeliveryRun $deliveryRun, Order $order, User $actor, ?int $visitOrder = null): DeliveryRunOrder
    {
        return DB::transaction(function () use ($deliveryRun, $order, $actor, $visitOrder): DeliveryRunOrder {
            $lockedRun = DeliveryRun::query()->lockForUpdate()->findOrFail($deliveryRun->id);
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($lockedRun->status !== DeliveryRunStatus::Draft) {
                throw ValidationException::withMessages([
                    'delivery_run' => ['Solo puedes asignar pedidos mientras la jornada está en borrador.'],
                ]);
            }

            if ($lockedOrder->status !== OrderStatus::Confirmed) {
                throw ValidationException::withMessages([
                    'order_id' => ['Solo se pueden asignar pedidos confirmados.'],
                ]);
            }

            if ($lockedOrder->warehouse_id !== $lockedRun->warehouse_id) {
                throw ValidationException::withMessages([
                    'order_id' => ['El pedido pertenece a una bodega diferente de la jornada.'],
                ]);
            }

            $lockedOrder->load(['items.inventoryReservation']);

            if ($lockedOrder->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'order_id' => ['El pedido no tiene productos para repartir.'],
                ]);
            }

            $hasMissingReservation = $lockedOrder->items->contains(
                fn ($item): bool => $item->inventoryReservation?->status !== InventoryReservationStatus::Active,
            );

            if ($hasMissingReservation) {
                throw ValidationException::withMessages([
                    'order_id' => ['El pedido no conserva todas sus reservas activas de inventario.'],
                ]);
            }

            $alreadyAssigned = DeliveryRunOrder::query()
                ->where('order_id', $lockedOrder->id)
                ->whereHas('deliveryRun', fn ($runs) => $runs->whereIn('status', [
                    DeliveryRunStatus::Draft->value,
                    DeliveryRunStatus::Preparing->value,
                    DeliveryRunStatus::Loaded->value,
                    DeliveryRunStatus::InTransit->value,
                    DeliveryRunStatus::AwaitingSettlement->value,
                ]))
                ->lockForUpdate()
                ->exists();

            if ($alreadyAssigned) {
                throw ValidationException::withMessages([
                    'order_id' => ['El pedido ya pertenece a otra jornada abierta.'],
                ]);
            }

            $nextVisitOrder = $visitOrder ?? ((int) $lockedRun->runOrders()->max('visit_order')) + 1;

            if ($lockedRun->runOrders()->where('visit_order', $nextVisitOrder)->exists()) {
                throw ValidationException::withMessages([
                    'visit_order' => ['Ya existe otra parada con ese número en la jornada.'],
                ]);
            }

            $runOrder = $lockedRun->runOrders()->create([
                'order_id' => $lockedOrder->id,
                'visit_order' => $nextVisitOrder,
                'status' => DeliveryOrderStatus::Pending,
                'requested_total' => $lockedOrder->total,
                'balance_due' => '0.0000',
            ]);

            foreach ($lockedOrder->items as $item) {
                $runOrder->items()->create([
                    'order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_presentation_id' => $item->product_presentation_id,
                    'product_sku' => $item->product_sku,
                    'product_name' => $item->product_name,
                    'presentation_name' => $item->presentation_name,
                    'base_unit_symbol' => $item->base_unit_symbol,
                    'conversion_factor' => $item->conversion_factor,
                    'unit_price' => $item->unit_price,
                    'requested_quantity' => $item->quantity,
                    'requested_base_quantity' => $item->base_quantity,
                ]);
            }

            $lockedOrder->forceFill(['status' => OrderStatus::Assigned])->save();
            $lockedOrder->statusHistory()->create([
                'from_status' => OrderStatus::Confirmed,
                'to_status' => OrderStatus::Assigned,
                'changed_by' => $actor->id,
                'reason' => "Asignado a la jornada {$lockedRun->run_number}.",
            ]);

            return $runOrder;
        });
    }
}
