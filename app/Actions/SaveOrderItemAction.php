<?php

namespace App\Actions;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductPresentation;
use App\Models\User;
use App\OrderPriceSource;
use App\Services\PresentationPriceResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveOrderItemAction
{
    public function __construct(
        private PresentationPriceResolver $priceResolver,
        private RecalculateOrderTotalsAction $recalculateTotals,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(
        Order $order,
        array $data,
        User $actor,
        bool $mayOverridePrice,
        ?OrderItem $item = null,
    ): OrderItem {
        return DB::transaction(function () use ($order, $data, $actor, $mayOverridePrice, $item): OrderItem {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! $lockedOrder->isDraft()) {
                throw ValidationException::withMessages([
                    'order' => ['Solo los pedidos en borrador se pueden editar.'],
                ]);
            }

            $presentation = ProductPresentation::query()
                ->with(['product.baseUnit', 'priceTiers'])
                ->findOrFail($data['product_presentation_id']);

            if (! $presentation->is_active || ! $presentation->is_sellable || ! $presentation->product->is_active) {
                throw ValidationException::withMessages([
                    'product_presentation_id' => ['La presentación seleccionada no está disponible para venta.'],
                ]);
            }

            $duplicateItem = $lockedOrder->items()
                ->where('product_presentation_id', $presentation->id)
                ->when($item !== null, fn ($query) => $query->whereKeyNot($item->id))
                ->exists();

            if ($duplicateItem) {
                throw ValidationException::withMessages([
                    'product_presentation_id' => ['La presentación ya está en el pedido; edita su línea para cambiar la cantidad.'],
                ]);
            }

            $price = $this->priceResolver->resolve(
                $presentation,
                (string) $data['quantity'],
                $lockedOrder->order_date,
            );
            $standardPrice = (string) $price['unit_price'];
            $requestedPrice = $data['unit_price'] ?? null;
            $hasOverride = $requestedPrice !== null
                && bccomp((string) $requestedPrice, $standardPrice, 4) !== 0;

            if ($hasOverride && ! $mayOverridePrice) {
                throw new AuthorizationException('No tienes permiso para cambiar el precio calculado.');
            }

            if ($hasOverride && empty($data['override_reason'])) {
                throw ValidationException::withMessages([
                    'override_reason' => ['Debes indicar el motivo del precio autorizado.'],
                ]);
            }

            $unitPrice = $hasOverride ? (string) $requestedPrice : $standardPrice;
            $quantity = (string) $data['quantity'];
            $attributes = [
                'product_id' => $presentation->product_id,
                'product_presentation_id' => $presentation->id,
                'price_tier_id' => $price['price_tier_id'],
                'product_sku' => $presentation->product->sku,
                'product_name' => $presentation->product->name,
                'presentation_name' => $presentation->name,
                'base_unit_symbol' => $presentation->product->baseUnit->symbol,
                'conversion_factor' => $presentation->conversion_factor,
                'quantity' => $quantity,
                'base_quantity' => bcmul($quantity, $presentation->conversion_factor, 6),
                'standard_unit_price' => $standardPrice,
                'unit_price' => $unitPrice,
                'price_source' => $hasOverride
                    ? OrderPriceSource::Override
                    : OrderPriceSource::from($price['source']),
                'price_overridden_by' => $hasOverride ? $actor->id : null,
                'override_reason' => $hasOverride ? $data['override_reason'] : null,
                'line_total' => bcmul($quantity, $unitPrice, 4),
                'notes' => $data['notes'] ?? null,
            ];

            if ($item === null) {
                $savedItem = $lockedOrder->items()->create($attributes);
            } else {
                $savedItem = $lockedOrder->items()->findOrFail($item->id);
                $savedItem->update($attributes);
            }

            $this->recalculateTotals->handle($lockedOrder);

            return $savedItem->refresh();
        });
    }
}
