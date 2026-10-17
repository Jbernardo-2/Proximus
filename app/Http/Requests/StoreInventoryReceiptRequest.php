<?php

namespace App\Http\Requests;

use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

/** A whole document with its lines, applied in one go: what the phone sends after working offline. */
class StoreInventoryReceiptRequest extends StoreInventoryDocumentRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.product_presentation_id' => [
                'required',
                'ulid',
                Rule::exists('product_presentations', 'id')->where(
                    fn (Builder $query): Builder => $query->where('is_active', true)->whereNull('deleted_at'),
                ),
            ],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,6'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'items.*.lot_number' => ['nullable', 'string', 'max:100'],
            'items.*.expiration_date' => ['nullable', 'date_format:Y-m-d'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
