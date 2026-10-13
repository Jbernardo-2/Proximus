<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-company-profile') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'commercial_name' => trim($this->string('commercial_name')->toString()),
            'legal_name' => $this->nullableTrimmedString('legal_name'),
            'rtn' => $this->nullableTrimmedString('rtn'),
            'phone' => $this->nullableTrimmedString('phone'),
            'whatsapp' => $this->nullableTrimmedString('whatsapp'),
            'email' => $this->normalizedEmail(),
            'address' => $this->nullableTrimmedString('address'),
            'slogan' => $this->nullableTrimmedString('slogan'),
            'remove_logo' => $this->boolean('remove_logo'),
        ]);
    }

    public function rules(): array
    {
        return [
            'commercial_name' => ['required', 'string', 'max:180'],
            'legal_name' => ['nullable', 'string', 'max:180'],
            'rtn' => ['nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'slogan' => ['nullable', 'string', 'max:255'],
            'remove_logo' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'commercial_name' => 'nombre comercial',
            'legal_name' => 'razón social',
            'rtn' => 'RTN',
            'phone' => 'teléfono',
            'whatsapp' => 'WhatsApp',
            'email' => 'correo electrónico',
            'address' => 'dirección',
            'logo' => 'logotipo',
            'slogan' => 'slogan',
        ];
    }

    private function nullableTrimmedString(string $key): ?string
    {
        $value = trim($this->string($key)->toString());

        return $value === '' ? null : $value;
    }

    private function normalizedEmail(): ?string
    {
        $email = $this->nullableTrimmedString('email');

        return $email === null ? null : mb_strtolower($email);
    }
}
