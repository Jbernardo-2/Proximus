<?php

namespace App\Services;

use App\DeliveryOrderStatus;
use App\Models\DeliveryRun;
use App\Models\DeliveryRunItem;
use App\Models\InventoryStock;
use Illuminate\Support\Collection;

/**
 * Products of a run that the warehouse cannot load because there is not enough
 * physical stock, with the orders that carry them. Prepared orders count what was
 * prepared; orders still to prepare count what was requested, so the warning shows
 * up before anyone tries to confirm the load.
 */
class DeliveryLoadShortages
{
    /**
     * @return Collection<int, array{product_id: string, product_name: string, unit: string, on_hand: string, needed: string, missing: string, orders: list<array{run_order_id: string, order_number: string, customer_name: string, quantity: string, presentation: string, prepared: bool}>}>
     */
    public function for(DeliveryRun $run): Collection
    {
        $items = DeliveryRunItem::query()
            ->with('deliveryRunOrder.order')
            ->whereHas('deliveryRunOrder', fn ($orders) => $orders->where('delivery_run_id', $run->id))
            ->get();
        $onHand = InventoryStock::query()
            ->where('warehouse_id', $run->warehouse_id)
            ->whereIn('product_id', $items->pluck('product_id')->unique())
            ->pluck('quantity_on_hand', 'product_id');

        return $items
            ->groupBy('product_id')
            ->map(function (Collection $lines, string $productId) use ($onHand): array {
                $needed = '0';
                $orders = [];

                foreach ($lines as $line) {
                    $prepared = $line->deliveryRunOrder->status === DeliveryOrderStatus::Prepared;
                    $base = (string) ($prepared ? $line->prepared_base_quantity : $line->requested_base_quantity);

                    if (bccomp($base, '0', 6) <= 0) {
                        continue;
                    }

                    $needed = bcadd($needed, $base, 6);
                    $orders[] = [
                        'run_order_id' => $line->delivery_run_order_id,
                        'order_number' => $line->deliveryRunOrder->order->order_number,
                        'customer_name' => $line->deliveryRunOrder->order->customer_name,
                        'quantity' => self::plain($prepared ? $line->prepared_quantity : $line->requested_quantity),
                        'presentation' => $line->presentation_name,
                        'prepared' => $prepared,
                    ];
                }

                $available = (string) ($onHand[$productId] ?? '0');

                return [
                    'product_id' => $productId,
                    'product_name' => $lines->first()->product_name,
                    'unit' => $lines->first()->base_unit_symbol,
                    'on_hand' => self::plain($available),
                    'needed' => self::plain($needed),
                    'missing' => self::plain(bcsub($needed, $available, 6)),
                    'orders' => $orders,
                ];
            })
            ->filter(fn (array $shortage): bool => bccomp($shortage['needed'], $shortage['on_hand'], 6) > 0)
            ->sortBy('product_name')
            ->values();
    }

    /**
     * One readable message per product short, for the validation error when confirming.
     *
     * @param  Collection<int, array<string, mixed>>  $shortages
     * @return list<string>
     */
    public function messages(Collection $shortages): array
    {
        return $shortages->map(fn (array $shortage): string => sprintf(
            '%s: hay %s %s en bodega y se necesitan %s. Está en %s.',
            $shortage['product_name'],
            $shortage['on_hand'],
            $shortage['unit'],
            $shortage['needed'],
            collect($shortage['orders'])->map(fn (array $order): string => "{$order['order_number']} ({$order['customer_name']}: {$order['quantity']} {$order['presentation']})")->join(', ', ' y '),
        ))->all();
    }

    /** "12.500000" -> "12.5", without going through floats. */
    private static function plain(mixed $value): string
    {
        $text = bcadd((string) $value, '0', 6);

        return str_contains($text, '.') ? (rtrim(rtrim($text, '0'), '.') ?: '0') : $text;
    }
}
