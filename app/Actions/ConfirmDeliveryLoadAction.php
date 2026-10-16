<?php

namespace App\Actions;

use App\DeliveryOrderStatus;
use App\DeliveryRunStatus;
use App\InventoryMovementType;
use App\InventoryReservationStatus;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunItem;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\User;
use App\OrderStatus;
use App\Services\DeliveryLoadShortages;
use App\Services\OperationalNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmDeliveryLoadAction
{
    public function __construct(
        private ApplyInventoryDeltaAction $applyDelta,
        private OperationalNotificationService $notifications,
        private DeliveryLoadShortages $shortages,
    ) {}

    public function handle(DeliveryRun $deliveryRun, User $actor): DeliveryRun
    {
        $loadedRun = DB::transaction(function () use ($deliveryRun, $actor): DeliveryRun {
            $run = DeliveryRun::query()->with('warehouse')->lockForUpdate()->findOrFail($deliveryRun->id);

            if ($run->status !== DeliveryRunStatus::Preparing) {
                throw ValidationException::withMessages([
                    'delivery_run' => ['Solo una jornada en preparación puede confirmar su carga.'],
                ]);
            }

            $runOrders = $run->runOrders()->with('order')->orderBy('visit_order')->lockForUpdate()->get();
            $items = DeliveryRunItem::query()
                ->with(['product', 'deliveryRunOrder.order'])
                ->whereHas('deliveryRunOrder', fn ($orders) => $orders->where('delivery_run_id', $run->id))
                ->orderBy('product_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($runOrders->isEmpty() || $items->isEmpty()) {
                throw ValidationException::withMessages([
                    'orders' => ['La jornada no tiene pedidos preparados para cargar.'],
                ]);
            }

            $notPrepared = $runOrders->first(fn ($runOrder): bool => $runOrder->status !== DeliveryOrderStatus::Prepared);

            if ($notPrepared !== null) {
                throw ValidationException::withMessages([
                    'orders' => ["Primero guarda la preparación del pedido {$notPrepared->order->order_number}."],
                ]);
            }

            foreach ($runOrders as $runOrder) {
                $hasPreparedItem = $items
                    ->where('delivery_run_order_id', $runOrder->id)
                    ->contains(fn (DeliveryRunItem $item): bool => bccomp((string) $item->prepared_base_quantity, '0', 6) > 0);

                if (! $hasPreparedItem) {
                    throw ValidationException::withMessages([
                        'items' => ["El pedido {$runOrder->order->order_number} no tiene mercancía preparada; retíralo o prepara al menos una línea."],
                    ]);
                }
            }

            // Every product short at once, with the orders that carry it, instead of
            // stopping at the first one with only its name.
            $shortages = $this->shortages->for($run);

            if ($shortages->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'stock' => [
                        $shortages->count() === 1
                            ? 'No alcanza la existencia de 1 producto para cargar esta jornada. Merma o retira esas líneas en su preparación:'
                            : "No alcanza la existencia de {$shortages->count()} productos para cargar esta jornada. Merma o retira esas líneas en su preparación:",
                        ...$this->shortages->messages($shortages),
                    ],
                ]);
            }

            $loadedTotal = '0.0000';

            foreach ($items as $item) {
                $reservation = InventoryReservation::query()
                    ->where('order_item_id', $item->order_item_id)
                    ->where('status', InventoryReservationStatus::Active->value)
                    ->lockForUpdate()
                    ->first();

                if ($reservation === null) {
                    throw ValidationException::withMessages([
                        'items' => ["La reserva de {$item->product_name} ya no está activa."],
                    ]);
                }

                $loadedBase = (string) $item->prepared_base_quantity;

                if (bccomp($loadedBase, (string) $reservation->base_quantity, 6) > 0) {
                    throw ValidationException::withMessages([
                        'items' => ["La carga de {$item->product_name} supera la cantidad reservada."],
                    ]);
                }

                $movementContext = [
                    'product_presentation_id' => $item->product_presentation_id,
                    'order_id' => $item->deliveryRunOrder->order_id,
                    'order_item_id' => $item->order_item_id,
                    'delivery_run_id' => $run->id,
                    'delivery_run_order_id' => $item->delivery_run_order_id,
                    'delivery_run_item_id' => $item->id,
                    'presentation_quantity' => $item->prepared_quantity,
                    'conversion_factor' => $item->conversion_factor,
                    'product_sku' => $item->product_sku,
                    'product_name' => $item->product_name,
                    'presentation_name' => $item->presentation_name,
                    'base_unit_symbol' => $item->base_unit_symbol,
                    'reference_number' => $run->run_number,
                ];

                if (bccomp($loadedBase, '0', 6) > 0) {
                    $this->applyDelta->handle(
                        $run->warehouse,
                        $item->product,
                        InventoryMovementType::DispatchLoad,
                        bcmul($loadedBase, '-1', 6),
                        bcmul($loadedBase, '-1', 6),
                        $actor,
                        [
                            ...$movementContext,
                            'reason' => "Mercancía cargada en la jornada {$run->run_number}.",
                        ],
                    );
                }

                $unloadedBase = bcsub((string) $reservation->base_quantity, $loadedBase, 6);

                if (bccomp($unloadedBase, '0', 6) > 0) {
                    $this->applyDelta->handle(
                        $run->warehouse,
                        $item->product,
                        InventoryMovementType::OrderReservationRelease,
                        '0.000000',
                        bcmul($unloadedBase, '-1', 6),
                        $actor,
                        [
                            ...$movementContext,
                            'presentation_quantity' => bcdiv($unloadedBase, (string) $item->conversion_factor, 6),
                            'reason' => "Cantidad no cargada y liberada en la jornada {$run->run_number}.",
                        ],
                    );
                }

                $reservation->forceFill([
                    'status' => InventoryReservationStatus::Fulfilled,
                    'fulfilled_at' => now(),
                    'fulfilled_by' => $actor->id,
                    'released_at' => null,
                    'released_by' => null,
                ])->save();
                $item->forceFill([
                    'loaded_quantity' => $item->prepared_quantity,
                    'loaded_base_quantity' => $loadedBase,
                ])->save();
                $loadedTotal = bcadd(
                    $loadedTotal,
                    bcmul((string) $item->prepared_quantity, (string) $item->unit_price, 4),
                    4,
                );
            }

            foreach ($runOrders as $runOrder) {
                $order = Order::query()->lockForUpdate()->findOrFail($runOrder->order_id);

                if ($order->status !== OrderStatus::Assigned) {
                    throw ValidationException::withMessages([
                        'orders' => ["El pedido {$order->order_number} cambió de estado durante la preparación."],
                    ]);
                }

                $runOrder->forceFill(['status' => DeliveryOrderStatus::Loaded])->save();
                $order->forceFill(['status' => OrderStatus::Loaded])->save();
                $order->statusHistory()->create([
                    'from_status' => OrderStatus::Assigned,
                    'to_status' => OrderStatus::Loaded,
                    'changed_by' => $actor->id,
                    'reason' => "Carga confirmada en la jornada {$run->run_number}.",
                ]);
            }

            $run->forceFill([
                'status' => DeliveryRunStatus::Loaded,
                'loaded_total' => $loadedTotal,
                'loaded_at' => now(),
                'loaded_by' => $actor->id,
            ])->save();
            $run->statusHistory()->create([
                'from_status' => DeliveryRunStatus::Preparing,
                'to_status' => DeliveryRunStatus::Loaded,
                'changed_by' => $actor->id,
                'reason' => 'Carga confirmada; las existencias salieron de bodega y quedaron en tránsito controlado.',
            ]);

            return $run;
        }, 3);

        $this->notifications->deliveryRunLoaded($loadedRun);

        return $loadedRun;
    }
}
