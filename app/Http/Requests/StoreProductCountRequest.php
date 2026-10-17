<?php

namespace App\Http\Requests;

use App\Models\InventoryCount;
use Illuminate\Validation\Rule;

class StoreProductCountRequest extends InventoryRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', InventoryCount::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['notes' => $this->nullableString('notes')]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'ulid', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'product_id' => ['required', 'ulid', Rule::exists('products', 'id')],
            'counted_quantity' => ['required', 'numeric', 'min:0', 'decimal:0,6'],
            // A few minutes of slack for a phone clock running ahead.
            'counted_at' => ['required', 'date', 'before:'.now()->addMinutes(10)->toIso8601String(), 'after:'.now()->subDays(30)->toIso8601String()],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [...parent::attributes(), 'product_id' => 'producto', 'counted_quantity' => 'cantidad contada', 'counted_at' => 'hora del conteo'];
    }
}
