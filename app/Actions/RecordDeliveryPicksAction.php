<?php

namespace App\Actions;

use App\DeliveryOrderStatus;
use App\DeliveryRunStatus;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunItem;
use App\Models\InventoryStock;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves some lines of a run as the keeper picks them, by product or by order, and
 * refuses right away what the warehouse does not have: the same check the load
 * makes, at the moment the quantity is entered. An order whose lines are all
 * reviewed becomes prepared.
 */
class RecordDeliveryPicksAction
{
    /** @param list<array{id: string, prepared_quantity: string}> $picks */
    public function handle(DeliveryRun $deliveryRun, array $picks, User $actor): DeliveryRun
    {
        return DB::transaction(function () use ($deliveryRun, $picks, $actor): DeliveryRun {
            $run = DeliveryRun::query()->lockForUpdate()->findOrFail($deliveryRun->id);

            if ($run->status !== DeliveryRunStatus::Preparing) {
                throw ValidationException::withMessages([
                    'delivery_run' => ['La preparación solo puede modificarse mientras la jornada está en preparación.'],
                ]);
            }

            $items = DeliveryRunItem::query()
                ->with(['product', 'presentation', 'deliveryRunOrder'])
                ->whereHas('deliveryRunOrder', fn ($orders) => $orders->where('delivery_run_id', $run->id))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $submitted = collect($picks)->keyBy('id');

            if ($submitted->keys()->contains(fn (string $id): bool => ! $items->has($id))) {
                throw ValidationException::withMessages(['items' => ['Una de las líneas no pertenece a esta jornada.']]);
            }

            foreach ($submitted as $id => $pick) {
                $item = $items[$id];
                $quantity = (string) $pick['prepared_quantity'];

                if (! in_array($item->deliveryRunOrder->status, [DeliveryOrderStatus::Pending, DeliveryOrderStatus::Prepared], true)) {
                    throw ValidationException::withMessages(['items' => ["El pedido de {$item->product_name} ya no admite cambios de preparación."]]);
                }

                if (bccomp($quantity, '0', 6) < 0 || bccomp($quantity, (string) $item->requested_quantity, 6) > 0) {
                    throw ValidationException::withMessages(['items' => ["La cantidad preparada de {$item->product_name} debe estar entre cero y lo solicitado."]]);
                }

                if (! ($item->presentation->is_base && $item->product->allows_decimal) && bccomp($quantity, bcadd($quantity, '0', 0), 6) !== 0) {
                    throw ValidationException::withMessages(['items' => ["La presentación {$item->presentation_name} de {$item->product_name} requiere cantidades enteras."]]);
                }

                $item->forceFill([
                    'prepared_quantity' => $quantity,
                    'prepared_base_quantity' => bcmul($quantity, (string) $item->conversion_factor, 6),
                    'picked_at' => now(),
                ]);
            }

            $this->ensureStock($run, $items, $submitted->keys()->all());

            foreach ($submitted->keys() as $id) {
                $items[$id]->save();
            }

            foreach ($items->groupBy('delivery_run_order_id') as $lines) {
                $order = $lines->first()->deliveryRunOrder;
                $reviewed = $lines->every(fn (DeliveryRunItem $line): bool => $line->picked_at !== null);
                $loadsSomething = $lines->contains(fn (DeliveryRunItem $line): bool => bccomp((string) $line->prepared_quantity, '0', 6) > 0);

                // An order with every line at zero stays pending: it has to leave the run, not the truck.
                $order->forceFill($reviewed && $loadsSomething
                    ? ['status' => DeliveryOrderStatus::Prepared, 'prepared_at' => $order->prepared_at ?? now(), 'prepared_by' => $order->prepared_by ?? $actor->id]
                    : ['status' => DeliveryOrderStatus::Pending, 'prepared_at' => null, 'prepared_by' => null])->save();
            }

            return $run->refresh();
        }, 3);
    }

    /**
     * What the reviewed lines take of each product they touch, in base units, against
     * the physical stock of the run's warehouse.
     *
     * @param  \Illuminate\Support\Collection<string, DeliveryRunItem>  $items
     * @param  list<string>  $changed
     */
    private function ensureStock(DeliveryRun $run, $items, array $changed): void
    {
        $products = $items->only($changed)->pluck('product_id')->unique();
        $onHand = InventoryStock::query()
            ->where('warehouse_id', $run->warehouse_id)
            ->whereIn('product_id', $products)
            ->lockForUpdate()
            ->pluck('quantity_on_hand', 'product_id');

        foreach ($products as $productId) {
            $lines = $items->where('product_id', $productId)->filter(fn (DeliveryRunItem $line): bool => $line->picked_at !== null);
            $taken = $lines->reduce(fn (string $sum, DeliveryRunItem $line): string => bcadd($sum, (string) $line->prepared_base_quantity, 6), '0');
            $available = (string) ($onHand[$productId] ?? '0');

            if (bccomp($taken, $available, 6) > 0) {
                $first = $lines->first();
                $plain = fn (string $value): string => rtrim(rtrim($value, '0'), '.') ?: '0';

                throw ValidationException::withMessages(['stock' => [
                    "Solo hay {$plain($available)} {$first->base_unit_symbol} de {$first->product_name} en bodega; con esto la jornada llevaría {$plain($taken)}.",
                ]]);
            }
        }
    }
}
