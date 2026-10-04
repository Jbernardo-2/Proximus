<?php

namespace App\Http\Requests;

use App\Models\InventoryDocument;
use Illuminate\Foundation\Http\FormRequest;

abstract class InventoryRequest extends FormRequest
{
    public function attributes(): array
    {
        return [
            'warehouse_id' => 'bodega',
            'supplier_id' => 'proveedor',
            'type' => 'tipo de movimiento',
            'occurred_on' => 'fecha del movimiento',
            'counted_on' => 'fecha del conteo',
            'external_reference' => 'referencia externa',
            'product_presentation_id' => 'presentación',
            'quantity' => 'cantidad',
            'unit_cost' => 'costo unitario',
            'lot_number' => 'número de lote',
            'expiration_date' => 'fecha de vencimiento',
            'reorder_point' => 'punto de reposición',
            'reason' => 'motivo',
            'notes' => 'notas',
        ];
    }

    protected function nullableString(string $key): ?string
    {
        $value = $this->string($key)->trim()->toString();

        return $value !== '' ? $value : null;
    }

    protected function nullableIdentifier(string $key): mixed
    {
        $value = $this->input($key);

        return $value === null || $value === '' ? null : $value;
    }

    protected function canModifyInventoryDocument(InventoryDocument $document): bool
    {
        $user = $this->user();

        if ($user === null || ! $user->can('update', $document)) {
            return false;
        }

        if (! $document->type->requiresAdjustmentPermission()) {
            return true;
        }

        return $user->canAdjustInventory()
            && (! $this->routeIs('api.v1.*') || $user->tokenCan('inventory:adjust'));
    }
}
