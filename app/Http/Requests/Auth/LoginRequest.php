<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [
            'email' => mb_strtolower(trim($this->string('email')->toString())),
        ];

        if ($this->has('device_name')) {
            $normalized['device_name'] = trim($this->string('device_name')->toString());
        }

        $this->merge($normalized);
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'max:128'],
            'remember' => ['sometimes', 'boolean'],
            'device_name' => ['sometimes', 'string', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return [
            'email' => 'correo electrónico',
            'password' => 'contraseña',
            'device_name' => 'nombre del dispositivo',
        ];
    }
}
