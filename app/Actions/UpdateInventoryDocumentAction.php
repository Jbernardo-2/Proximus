<?php

namespace App\Actions;

use App\InventoryDocumentType;
use App\Models\InventoryDocument;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateInventoryDocumentAction
{
    /** @param array<string, mixed> $data */
    public function handle(InventoryDocument $document, array $data, User $actor): InventoryDocument
    {
        return DB::transaction(function () use ($document, $data, $actor): InventoryDocument {
            $lockedDocument = InventoryDocument::query()->lockForUpdate()->findOrFail($document->id);

            if (! $lockedDocument->isDraft()) {
                throw ValidationException::withMessages([
                    'document' => ['Solo se puede editar un movimiento en borrador.'],
                ]);
            }

            $type = InventoryDocumentType::from($data['type']);

            if ($type->requiresAdjustmentPermission() && ! $actor->canAdjustInventory()) {
                throw new AuthorizationException('No tienes permiso para registrar ajustes manuales de inventario.');
            }

            $warehouse = Warehouse::query()->findOrFail($data['warehouse_id']);

            if (! $warehouse->is_active) {
                throw ValidationException::withMessages([
                    'warehouse_id' => ['La bodega seleccionada no está activa.'],
                ]);
            }

            $supplierId = $data['supplier_id'] ?? null;

            if ($supplierId !== null && ! Supplier::query()->active()->whereKey($supplierId)->exists()) {
                throw ValidationException::withMessages([
                    'supplier_id' => ['El proveedor seleccionado no está disponible.'],
                ]);
            }

            $lockedDocument->update([
                'warehouse_id' => $warehouse->id,
                'supplier_id' => $supplierId,
                'type' => $type,
                'occurred_on' => $data['occurred_on'],
                'external_reference' => $data['external_reference'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            return $lockedDocument->refresh();
        });
    }
}
