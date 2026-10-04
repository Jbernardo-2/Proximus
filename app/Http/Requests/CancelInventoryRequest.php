<?php

namespace App\Http\Requests;

use App\Models\InventoryCount;
use App\Models\InventoryDocument;

class CancelInventoryRequest extends InventoryRequest
{
    public function authorize(): bool
    {
        $model = $this->route('inventoryDocument') ?? $this->route('inventoryCount');
        $user = $this->user();

        if ((! $model instanceof InventoryDocument && ! $model instanceof InventoryCount)
            || $user === null
            || ! $user->can('delete', $model)) {
            return false;
        }

        if (! $model instanceof InventoryDocument || ! $model->type->requiresAdjustmentPermission()) {
            return true;
        }

        return $user->canAdjustInventory()
            && (! $this->routeIs('api.v1.*') || $user->tokenCan('inventory:adjust'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['reason' => $this->nullableString('reason')]);
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:1000']];
    }
}
