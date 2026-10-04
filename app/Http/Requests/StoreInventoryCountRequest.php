<?php

namespace App\Http\Requests;

use App\Models\InventoryCount;
use Illuminate\Validation\Rule;

class StoreInventoryCountRequest extends InventoryRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', InventoryCount::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'counted_on' => $this->input('counted_on') ?: now()->toDateString(),
            'notes' => $this->nullableString('notes'),
        ]);
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'ulid', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'counted_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
