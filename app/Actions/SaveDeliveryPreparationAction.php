<?php

namespace App\Actions;

use App\DeliveryOrderStatus;
use App\DeliveryRunStatus;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveDeliveryPreparationAction
{
    /** @param list<array{id: string, prepared_quantity: mixed}> $items */
    public function handle(DeliveryRun $deliveryRun, array $items, ?User $actor = null): DeliveryRun
    {
        return DB::transaction(function () use ($deliveryRun, $items, $actor): DeliveryRun {
            $run = DeliveryRun::query()->lockForUpdate()->findOrFail($deliveryRun->id);

            if ($run->status !== DeliveryRunStatus::Preparing) {
                throw ValidationException::withMessages([
                    'delivery_run' => ['La preparación solo puede modificarse mientras la jornada está en preparación.'],
                ]);
            }

            $runItems = DeliveryRunItem::query()
                ->with(['product', 'presentation'])
                ->whereHas('deliveryRunOrder', fn ($orders) => $orders->where('delivery_run_id', $run->id))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $submitted = collect($items)->keyBy('id');

            if ($submitted->count() !== $runItems->count() || $runItems->contains(fn (DeliveryRunItem $item): bool => ! $submitted->has($item->id))) {
                throw ValidationException::withMessages([
                    'items' => ['Envía una cantidad preparada para cada línea de la jornada.'],
                ]);
            }

            foreach ($runItems as $item) {
                $quantity = (string) $submitted->get($item->id)['prepared_quantity'];

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

                $item->forceFill([
                    'prepared_quantity' => $quantity,
                    'prepared_base_quantity' => bcmul($quantity, (string) $item->conversion_factor, 6),
                ])->save();
            }

            $run->runOrders()->update([
                'status' => DeliveryOrderStatus::Prepared->value,
                'prepared_at' => now(),
                'prepared_by' => $actor?->id,
            ]);

            return $run->refresh();
        });
    }
}
