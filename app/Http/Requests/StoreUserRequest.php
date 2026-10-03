<?php

namespace App\Http\Requests;

use App\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-users') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [
            'name' => trim($this->string('name')->toString()),
            'email' => mb_strtolower(trim($this->string('email')->toString())),
        ];

        if ($this->has('is_active')) {
            $normalized['is_active'] = $this->boolean('is_active');
        }

        $this->merge($normalized);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::enum(UserRole::class)],
            'is_active' => ['required', 'boolean'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'email' => 'correo electrónico',
            'role' => 'rol',
            'is_active' => 'estado activo',
            'password' => 'contraseña',
        ];
    }
}
