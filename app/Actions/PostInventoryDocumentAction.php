<?php

namespace App\Actions;

use App\InventoryDocumentStatus;
use App\InventoryMovementType;
use App\Models\InventoryDocument;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PostInventoryDocumentAction
{
    public function __construct(private ApplyInventoryDeltaAction $applyDelta) {}

    public function handle(InventoryDocument $document, User $actor): InventoryDocument
    {
        return DB::transaction(function () use ($document, $actor): InventoryDocument {
            $lockedDocument = InventoryDocument::query()->lockForUpdate()->findOrFail($document->id);

            if (! $lockedDocument->isDraft()) {
                throw ValidationException::withMessages([
                    'document' => ['Solo se puede aplicar un movimiento en borrador.'],
                ]);
            }

            if ($lockedDocument->type->requiresAdjustmentPermission() && ! $actor->canAdjustInventory()) {
                throw new AuthorizationException('No tienes permiso para aplicar ajustes manuales de inventario.');
            }

            $lockedDocument->load(['warehouse', 'items.product']);

            if ($lockedDocument->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => ['Agrega al menos un producto antes de aplicar el movimiento.'],
                ]);
            }

            $movementType = InventoryMovementType::fromDocumentType($lockedDocument->type);
            $occurredAt = CarbonImmutable::parse($lockedDocument->occurred_on->toDateString())->startOfDay();

            foreach ($lockedDocument->items as $item) {
                $onHandDelta = bcmul(
                    (string) $item->base_quantity,
                    (string) $lockedDocument->type->onHandSign(),
                    6,
                );

                $this->applyDelta->handle(
                    $lockedDocument->warehouse,
                    $item->product,
                    $movementType,
                    $onHandDelta,
                    '0.000000',
                    $actor,
                    [
                        'product_presentation_id' => $item->product_presentation_id,
                        'inventory_document_id' => $lockedDocument->id,
                        'occurred_at' => $occurredAt,
                        'presentation_quantity' => $item->quantity,
                        'conversion_factor' => $item->conversion_factor,
                        'product_sku' => $item->product_sku,
                        'product_name' => $item->product_name,
                        'presentation_name' => $item->presentation_name,
                        'base_unit_symbol' => $item->base_unit_symbol,
                        'reference_number' => $lockedDocument->document_number,
                        'lot_number' => $item->lot_number,
                        'expiration_date' => $item->expiration_date,
                        'reason' => $lockedDocument->notes ?? $lockedDocument->type->label(),
                    ],
                );
            }

            $lockedDocument->forceFill([
                'status' => InventoryDocumentStatus::Posted,
                'posted_at' => now(),
                'posted_by' => $actor->id,
            ])->save();

            return $lockedDocument->refresh();
        }, 3);
    }
}
