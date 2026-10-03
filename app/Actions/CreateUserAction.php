<?php

namespace App\Actions;

use App\Models\User;

class CreateUserAction
{
    public function handle(array $data): User
    {
        return User::query()->create([
            ...$data,
            'email_verified_at' => now(),
        ]);
    }
}
