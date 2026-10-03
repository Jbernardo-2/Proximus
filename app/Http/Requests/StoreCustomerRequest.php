<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreCustomerRequest extends OperationsRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => $this->string('code')->trim()->upper()->toString(),
            'business_name' => $this->string('business_name')->trim()->toString(),
            'business_type' => $this->nullableString('business_type'),
            'contact_name' => $this->nullableString('contact_name'),
            'phone' => $this->nullableString('phone'),
            'whatsapp' => $this->nullableString('whatsapp'),
            'email' => $this->nullableString('email'),
            'address' => $this->string('address')->trim()->toString(),
            'reference' => $this->nullableString('reference'),
            'notes' => $this->nullableString('notes'),
        ]);
    }

    public function rules(): array
    {
        $customer = $this->route('customer');

        return [
            'code' => ['required', 'string', 'max:40', Rule::unique('customers', 'code')->ignore($customer)],
            'business_name' => ['required', 'string', 'max:255'],
            'business_type' => ['nullable', 'string', 'max:80'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'whatsapp' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'address' => ['required', 'string', 'max:2000'],
            'reference' => ['nullable', 'string', 'max:2000'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function ability(): string
    {
        return 'manage-customers';
    }

    private function nullableString(string $key): ?string
    {
        $value = $this->string($key)->trim()->toString();

        return $value !== '' ? $value : null;
    }
}
