<?php

namespace App\Http\Requests;

use App\InventoryDocumentType;
use App\Models\InventoryDocument;

class UpdateInventoryDocumentRequest extends StoreInventoryDocumentRequest
{
    public function authorize(): bool
    {
        $document = $this->route('inventoryDocument');
        $user = $this->user();
        $type = InventoryDocumentType::tryFrom($this->string('type')->toString());

        if (! $document instanceof InventoryDocument
            || $user === null
            || ! $this->canModifyInventoryDocument($document)) {
            return false;
        }

        if ($type?->requiresAdjustmentPermission() !== true) {
            return true;
        }

        return $user->canAdjustInventory()
            && (! $this->routeIs('api.v1.*') || $user->tokenCan('inventory:adjust'));
    }
}
