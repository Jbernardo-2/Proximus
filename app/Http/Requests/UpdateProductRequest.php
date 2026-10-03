<?php

namespace App\Http\Requests;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends CatalogRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'sku' => $this->string('sku')->trim()->upper()->toString(),
            'slug' => Str::slug($this->string('name')->toString()),
        ]);
    }

    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'category_id' => ['required', 'ulid', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'brand_id' => ['nullable', 'ulid', Rule::exists('brands', 'id')->whereNull('deleted_at')],
            'base_unit_id' => ['required', 'ulid', Rule::exists('measurement_units', 'id')->whereNull('deleted_at')],
            'sku' => ['required', 'string', 'max:80', Rule::unique('products', 'sku')->ignore($product)],
            'name' => ['required', 'string', 'max:180'],
            'slug' => ['required', 'string', 'max:200', Rule::unique('products', 'slug')->ignore($product)],
            'description' => ['nullable', 'string', 'max:4000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'remove_image' => ['sometimes', 'boolean'],
            'allows_decimal' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
