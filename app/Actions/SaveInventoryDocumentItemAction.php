<?php

namespace App\Actions;

use App\Models\InventoryDocument;
use App\Models\InventoryDocumentItem;
use App\Models\ProductPresentation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveInventoryDocumentItemAction
{
    /** @param array<string, mixed> $data */
    public function handle(
        InventoryDocument $document,
        array $data,
        ?InventoryDocumentItem $item = null,
    ): InventoryDocumentItem {
        return DB::transaction(function () use ($document, $data, $item): InventoryDocumentItem {
            $lockedDocument = InventoryDocument::query()->lockForUpdate()->findOrFail($document->id);

            if (! $lockedDocument->isDraft()) {
                throw ValidationException::withMessages([
                    'document' => ['Solo se pueden editar líneas de un movimiento en borrador.'],
                ]);
            }

            $presentation = ProductPresentation::query()
                ->with(['product.baseUnit'])
                ->findOrFail($data['product_presentation_id']);
            $product = $presentation->product;

            if (! $presentation->is_active || ! $product->is_active) {
                throw ValidationException::withMessages([
                    'product_presentation_id' => ['La presentación seleccionada no está activa.'],
                ]);
            }

            if ($product->tracks_lots && empty($data['lot_number'])) {
                throw ValidationException::withMessages([
                    'lot_number' => ['Este producto requiere número de lote.'],
                ]);
            }

            if ($product->tracks_expiration && empty($data['expiration_date'])) {
                throw ValidationException::withMessages([
                    'expiration_date' => ['Este producto requiere fecha de vencimiento.'],
                ]);
            }

            $quantity = (string) $data['quantity'];

            if (! $product->allows_decimal
                && bccomp($quantity, bcadd($quantity, '0', 0), 6) !== 0) {
                throw ValidationException::withMessages([
                    'quantity' => ['Este producto solo admite cantidades enteras.'],
                ]);
            }

            $duplicate = $lockedDocument->items()
                ->where('product_presentation_id', $presentation->id)
                ->where(function ($query) use ($data): void {
                    $lotNumber = $data['lot_number'] ?? null;
                    $expirationDate = $data['expiration_date'] ?? null;
                    $lotNumber === null ? $query->whereNull('lot_number') : $query->where('lot_number', $lotNumber);
                    $expirationDate === null
                        ? $query->whereNull('expiration_date')
                        : $query->whereDate('expiration_date', $expirationDate);
                })
                ->when($item !== null, fn ($query) => $query->where('id', '!=', $item->id))
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'product_presentation_id' => ['Esta presentación y lote ya están en el documento; edita la línea existente.'],
                ]);
            }

            $attributes = [
                'product_id' => $product->id,
                'product_presentation_id' => $presentation->id,
                'product_sku' => $product->sku,
                'product_name' => $product->name,
                'presentation_name' => $presentation->name,
                'base_unit_symbol' => $product->baseUnit->symbol,
                'conversion_factor' => $presentation->conversion_factor,
                'quantity' => $quantity,
                'base_quantity' => bcmul($quantity, (string) $presentation->conversion_factor, 6),
                'unit_cost' => $data['unit_cost'] ?? null,
                'lot_number' => $data['lot_number'] ?? null,
                'expiration_date' => $data['expiration_date'] ?? null,
                'notes' => $data['notes'] ?? null,
            ];

            if ($item === null) {
                return $lockedDocument->items()->create($attributes);
            }

            $savedItem = $lockedDocument->items()->findOrFail($item->id);
            $savedItem->update($attributes);

            return $savedItem->refresh();
        });
    }
}
