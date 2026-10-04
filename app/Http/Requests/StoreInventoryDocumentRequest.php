<?php

namespace App\Http\Requests;

use App\InventoryDocumentType;
use App\Models\InventoryDocument;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

class StoreInventoryDocumentRequest extends InventoryRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $type = InventoryDocumentType::tryFrom($this->string('type')->toString());

        if ($user === null || ! $user->can('create', InventoryDocument::class)) {
            return false;
        }

        if ($type?->requiresAdjustmentPermission() !== true) {
            return true;
        }

        return $user->canAdjustInventory()
            && (! $this->routeIs('api.v1.*') || $user->tokenCan('inventory:adjust'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'supplier_id' => $this->nullableIdentifier('supplier_id'),
            'occurred_on' => $this->input('occurred_on') ?: now()->toDateString(),
            'external_reference' => $this->nullableString('external_reference'),
            'notes' => $this->nullableString('notes'),
        ]);
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => [
                'required',
                'ulid',
                Rule::exists('warehouses', 'id')->where('is_active', true),
            ],
            'supplier_id' => [
                'nullable',
                'ulid',
                Rule::exists('suppliers', 'id')->where(
                    fn (Builder $query): Builder => $query->where('is_active', true)->whereNull('deleted_at'),
                ),
            ],
            'type' => ['required', Rule::enum(InventoryDocumentType::class)],
            'occurred_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'external_reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
