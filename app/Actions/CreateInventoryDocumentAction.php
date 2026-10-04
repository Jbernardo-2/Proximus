<?php

namespace App\Actions;

use App\InventoryDocumentStatus;
use App\InventoryDocumentType;
use App\Models\InventoryDocument;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateInventoryDocumentAction
{
    public function __construct(private GenerateInventoryNumberAction $generateNumber) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data, User $actor): InventoryDocument
    {
        return DB::transaction(function () use ($data, $actor): InventoryDocument {
            $type = InventoryDocumentType::from($data['type']);

            if ($type->requiresAdjustmentPermission() && ! $actor->canAdjustInventory()) {
                throw new AuthorizationException('No tienes permiso para registrar ajustes manuales de inventario.');
            }

            $warehouse = Warehouse::query()->lockForUpdate()->findOrFail($data['warehouse_id']);

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

            $occurredOn = CarbonImmutable::parse($data['occurred_on'])->startOfDay();

            return InventoryDocument::query()->create([
                'document_number' => $this->generateNumber->handle(
                    'document-'.$type->value,
                    $type->prefix(),
                    $occurredOn,
                ),
                'warehouse_id' => $warehouse->id,
                'supplier_id' => $supplierId,
                'type' => $type,
                'status' => InventoryDocumentStatus::Draft,
                'occurred_on' => $occurredOn,
                'external_reference' => $data['external_reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);
        });
    }
}
