<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Models\ProductSupplier;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductSupplierRequest extends CatalogRequest
{
    protected function prepareForValidation(): void
    {
        $sku = $this->string('supplier_sku')->trim()->upper()->toString();
        $this->merge(['supplier_sku' => $sku !== '' ? $sku : null]);
    }

    public function rules(): array
    {
        $product = $this->route('product');
        $source = $this->route('productSupplier');

        if (! $product instanceof Product && $source instanceof ProductSupplier) {
            $product = $source->product;
        }

        return [
            'supplier_id' => ['required', 'ulid', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'product_presentation_id' => [
                'required',
                'ulid',
                Rule::exists('product_presentations', 'id')
                    ->where('product_id', $product?->id)
                    ->whereNull('deleted_at'),
            ],
            'supplier_sku' => ['nullable', 'string', 'max:100'],
            'cost_price' => ['required', 'numeric', 'min:0', 'decimal:0,4'],
            'is_preferred' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['supplier_id', 'product_presentation_id'])) {
                return;
            }

            $source = $this->route('productSupplier');
            $query = $source instanceof ProductSupplier
                ? ProductSupplier::withTrashed()
                : ProductSupplier::query();
            $exists = $query
                ->where('supplier_id', $this->input('supplier_id'))
                ->where('product_presentation_id', $this->input('product_presentation_id'))
                ->when($source instanceof ProductSupplier, fn ($query) => $query->whereKeyNot($source->id))
                ->exists();

            if ($exists) {
                $validator->errors()->add('supplier_id', 'Este proveedor ya está vinculado a la presentación seleccionada.');
            }
        }];
    }
}
