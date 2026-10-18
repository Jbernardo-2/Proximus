<?php

namespace App\Http\Requests;

use App\Models\User;
use App\UserRole;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

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

    /** Says who already has the address, so the admin reactivates them instead of creating a duplicate. */
    protected function emailNotTaken(?int $ignoreId = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ignoreId): void {
            $owner = User::query()->where('email', $value)->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))->first();

            if ($owner !== null) {
                $fail($owner->emailTakenMessage());
            }
        };
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', $this->emailNotTaken()],
            'role' => ['required', Rule::enum(UserRole::class)],
            'is_active' => ['required', 'boolean'],
            'password' => ['required', 'string', Password::default()],
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
