<?php

namespace App\Http\Requests;

use App\Models\InventoryCount;

class PostInventoryCountRequest extends InventoryRequest
{
    public function authorize(): bool
    {
        $count = $this->route('inventoryCount');

        return $count instanceof InventoryCount && ($this->user()?->can('post', $count) ?? false);
    }

    public function rules(): array
    {
        return [];
    }
}
