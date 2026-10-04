<?php

namespace App\Http\Requests;

use App\Models\InventoryDocument;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

class StoreInventoryDocumentItemRequest extends InventoryRequest
{
    public function authorize(): bool
    {
        $document = $this->route('inventoryDocument');

        return $document instanceof InventoryDocument && $this->canModifyInventoryDocument($document);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'unit_cost' => $this->nullableIdentifier('unit_cost'),
            'lot_number' => $this->nullableString('lot_number'),
            'expiration_date' => $this->nullableIdentifier('expiration_date'),
            'notes' => $this->nullableString('notes'),
        ]);
    }

    public function rules(): array
    {
        return [
            'product_presentation_id' => [
                'required',
                'ulid',
                Rule::exists('product_presentations', 'id')->where(
                    fn (Builder $query): Builder => $query->where('is_active', true)->whereNull('deleted_at'),
                ),
            ],
            'quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,6'],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'lot_number' => ['nullable', 'string', 'max:100'],
            'expiration_date' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
