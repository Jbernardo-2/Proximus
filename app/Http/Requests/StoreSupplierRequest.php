<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreSupplierRequest extends CatalogRequest
{
    protected function prepareForValidation(): void
    {
        $code = $this->string('code')->trim()->upper()->toString();
        $this->merge(['code' => $code !== '' ? $code : null]);
    }

    public function rules(): array
    {
        $supplier = $this->route('supplier');

        return [
            'code' => ['nullable', 'string', 'max:40', Rule::unique('suppliers', 'code')->ignore($supplier)],
            'name' => ['required', 'string', 'max:160', Rule::unique('suppliers', 'name')->ignore($supplier)],
            'contact_name' => ['nullable', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
