<?php

namespace App\Actions;

use App\DeliveryOrderStatus;
use App\DeliveryRunStatus;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunItem;
use App\Models\DeliveryRunOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveDeliveryOrderPreparationAction
{
    /** @param list<array{id: string, prepared_quantity: mixed}> $items */
    public function handle(
        DeliveryRun $deliveryRun,
        DeliveryRunOrder $deliveryRunOrder,
        array $items,
        User $actor,
    ): DeliveryRunOrder {
        return DB::transaction(function () use ($deliveryRun, $deliveryRunOrder, $items, $actor): DeliveryRunOrder {
            $run = DeliveryRun::query()->lockForUpdate()->findOrFail($deliveryRun->id);

            if ($run->status !== DeliveryRunStatus::Preparing) {
                throw ValidationException::withMessages([
                    'delivery_run' => ['La preparación solo puede modificarse mientras la jornada está en preparación.'],
                ]);
            }

            $runOrder = DeliveryRunOrder::query()
                ->where('delivery_run_id', $run->id)
                ->whereKey($deliveryRunOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($runOrder->status, [DeliveryOrderStatus::Pending, DeliveryOrderStatus::Prepared], true)) {
                throw ValidationException::withMessages([
                    'order' => ['Este pedido ya no admite cambios de preparación.'],
                ]);
            }

            $runItems = DeliveryRunItem::query()
                ->with(['product', 'presentation'])
                ->where('delivery_run_order_id', $runOrder->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $submitted = collect($items)->keyBy('id');

            if ($submitted->count() !== $runItems->count() || $runItems->contains(fn (DeliveryRunItem $item): bool => ! $submitted->has($item->id))) {
                throw ValidationException::withMessages([
                    'items' => ['Envía una cantidad preparada para cada línea de este pedido.'],
                ]);
            }

            $hasPreparedItem = false;

            foreach ($runItems as $item) {
                $quantity = (string) $submitted->get($item->id)['prepared_quantity'];
                $this->validateQuantity($item, $quantity);
                $hasPreparedItem = $hasPreparedItem || bccomp($quantity, '0', 6) > 0;
                $item->forceFill([
                    'prepared_quantity' => $quantity,
                    'prepared_base_quantity' => bcmul($quantity, (string) $item->conversion_factor, 6),
                ])->save();
            }

            if (! $hasPreparedItem) {
                throw ValidationException::withMessages([
                    'items' => ['Prepara al menos una línea. Si el pedido no puede salir, retíralo de la jornada antes de continuar.'],
                ]);
            }

            $runOrder->forceFill([
                'status' => DeliveryOrderStatus::Prepared,
                'prepared_at' => now(),
                'prepared_by' => $actor->id,
            ])->save();

            return $runOrder->refresh();
        }, 3);
    }

    private function validateQuantity(DeliveryRunItem $item, string $quantity): void
    {
        if (bccomp($quantity, '0', 6) < 0 || bccomp($quantity, (string) $item->requested_quantity, 6) > 0) {
            throw ValidationException::withMessages([
                'items' => ["La cantidad preparada de {$item->product_name} debe estar entre cero y lo solicitado."],
            ]);
        }

        $allowsDecimal = $item->presentation->is_base && $item->product->allows_decimal;

        if (! $allowsDecimal && bccomp($quantity, bcadd($quantity, '0', 0), 6) !== 0) {
            throw ValidationException::withMessages([
                'items' => ["La presentación {$item->presentation_name} de {$item->product_name} requiere cantidades enteras."],
            ]);
        }
    }
}
