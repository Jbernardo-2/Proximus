<?php

namespace App\Http\Requests;

use App\Models\InventoryStock;

class UpdateInventoryStockRequest extends InventoryRequest
{
    public function authorize(): bool
    {
        $stock = $this->route('inventoryStock');

        return $stock instanceof InventoryStock && ($this->user()?->can('update', $stock) ?? false);
    }

    public function rules(): array
    {
        return ['reorder_point' => ['required', 'numeric', 'min:0', 'decimal:0,6']];
    }
}
