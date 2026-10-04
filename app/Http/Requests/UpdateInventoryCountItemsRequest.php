<?php

namespace App\Http\Requests;

use App\Models\InventoryCount;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

class UpdateInventoryCountItemsRequest extends InventoryRequest
{
    public function authorize(): bool
    {
        $count = $this->route('inventoryCount');

        return $count instanceof InventoryCount && ($this->user()?->can('update', $count) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))->map(function ($item): array {
            $quantity = $item['counted_quantity'] ?? null;
            $notes = trim((string) ($item['notes'] ?? ''));

            return [
                'id' => $item['id'] ?? null,
                'counted_quantity' => $quantity === '' ? null : $quantity,
                'notes' => $notes === '' ? null : $notes,
            ];
        })->values()->all();

        $this->merge(['items' => $items]);
    }

    public function rules(): array
    {
        /** @var InventoryCount $count */
        $count = $this->route('inventoryCount');

        return [
            'items' => ['required', 'array', 'min:1', 'max:2000'],
            'items.*.id' => [
                'required',
                'ulid',
                'distinct',
                Rule::exists('inventory_count_items', 'id')->where(
                    fn (Builder $query): Builder => $query->where('inventory_count_id', $count->id),
                ),
            ],
            'items.*.counted_quantity' => ['nullable', 'numeric', 'min:0', 'decimal:0,6'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
