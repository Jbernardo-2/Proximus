<?php

namespace App\Http\Requests;

use App\Models\InventoryDocument;

class DeleteInventoryDocumentItemRequest extends InventoryRequest
{
    public function authorize(): bool
    {
        $document = $this->route('inventoryDocument');

        return $document instanceof InventoryDocument && $this->canModifyInventoryDocument($document);
    }

    public function rules(): array
    {
        return [];
    }
}
