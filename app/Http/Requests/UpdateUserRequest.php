<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends StoreUserRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        if ($this->input('password') === '') {
            $this->merge([
                'password' => null,
                'password_confirmation' => null,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'email' => ['required', 'email:rfc', 'max:255', $this->emailNotTaken($this->route('user')?->getKey())],
            'password' => ['sometimes', 'nullable', 'string', Password::default()],
        ];
    }
}
