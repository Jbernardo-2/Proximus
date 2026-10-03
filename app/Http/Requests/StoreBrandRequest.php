<?php

namespace App\Http\Requests;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreBrandRequest extends CatalogRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['slug' => Str::slug($this->string('name')->toString())]);
    }

    public function rules(): array
    {
        $brand = $this->route('brand');

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('brands', 'name')->ignore($brand)],
            'slug' => ['required', 'string', 'max:140', Rule::unique('brands', 'slug')->ignore($brand)],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
