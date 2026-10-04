<?php

namespace App\Http\Requests;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductRequest extends CatalogRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'sku' => $this->string('sku')->trim()->upper()->toString(),
            'slug' => Str::slug($this->string('name')->toString()),
            'tracks_lots' => $this->boolean('tracks_lots'),
            'tracks_expiration' => $this->boolean('tracks_expiration'),
        ]);
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'ulid', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'brand_id' => ['nullable', 'ulid', Rule::exists('brands', 'id')->whereNull('deleted_at')],
            'base_unit_id' => ['required', 'ulid', Rule::exists('measurement_units', 'id')->whereNull('deleted_at')],
            'sku' => ['required', 'string', 'max:80', Rule::unique('products', 'sku')],
            'name' => ['required', 'string', 'max:180'],
            'slug' => ['required', 'string', 'max:200', Rule::unique('products', 'slug')],
            'description' => ['nullable', 'string', 'max:4000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'allows_decimal' => ['required', 'boolean'],
            'tracks_lots' => ['required', 'boolean'],
            'tracks_expiration' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'base_presentation_name' => ['required', 'string', 'max:120'],
            'base_barcode' => ['nullable', 'string', 'max:80', Rule::unique('product_presentations', 'barcode')],
            'base_sale_price' => ['required', 'numeric', 'min:0', 'decimal:0,4'],
            'base_is_sellable' => ['required', 'boolean'],
            'base_is_purchasable' => ['required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->boolean('tracks_expiration') && ! $this->boolean('tracks_lots')) {
                $validator->errors()->add('tracks_lots', 'Para controlar vencimientos también debes activar el control por lotes.');
            }
        }];
    }
}
