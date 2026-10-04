<?php

namespace App\Http\Requests;

use App\Models\InventoryDocument;

class PostInventoryDocumentRequest extends InventoryRequest
{
    public function authorize(): bool
    {
        $document = $this->route('inventoryDocument');
        $user = $this->user();

        if (! $document instanceof InventoryDocument
            || $user === null
            || ! $user->can('post', $document)) {
            return false;
        }

        if (! $document->type->requiresAdjustmentPermission()) {
            return true;
        }

        return $user->canAdjustInventory()
            && (! $this->routeIs('api.v1.*') || $user->tokenCan('inventory:adjust'));
    }

    public function rules(): array
    {
        return [];
    }
}
