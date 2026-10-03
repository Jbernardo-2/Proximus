<?php

namespace App\Actions;

use App\Models\User;
use App\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateUserAction
{
    public function handle(User $actor, User $user, array $data): User
    {
        return DB::transaction(function () use ($actor, $user, $data): User {
            $managedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $newRole = UserRole::from($data['role']);
            $willBeActive = (bool) $data['is_active'];

            $this->ensureActorKeepsAdministrativeAccess($actor, $managedUser, $newRole, $willBeActive);
            $this->ensureAnActiveAdministratorRemains($managedUser, $newRole, $willBeActive);

            $passwordChanged = array_key_exists('password', $data) && filled($data['password']);

            if (! $passwordChanged) {
                unset($data['password']);
            }

            $accessChanged = $managedUser->role !== $newRole || $managedUser->is_active !== $willBeActive;

            $managedUser->update($data);

            if ($accessChanged || $passwordChanged) {
                $managedUser->tokens()->delete();

                if (! $managedUser->is($actor)) {
                    DB::table('sessions')->where('user_id', $managedUser->getKey())->delete();
                }
            }

            return $managedUser->refresh();
        });
    }

    private function ensureActorKeepsAdministrativeAccess(User $actor, User $managedUser, UserRole $newRole, bool $willBeActive): void
    {
        if (! $managedUser->is($actor)) {
            return;
        }

        if (! $willBeActive) {
            throw ValidationException::withMessages([
                'is_active' => ['No puedes desactivar tu propia cuenta.'],
            ]);
        }

        if ($newRole !== UserRole::Admin) {
            throw ValidationException::withMessages([
                'role' => ['No puedes retirar el rol administrador de tu propia cuenta.'],
            ]);
        }
    }

    private function ensureAnActiveAdministratorRemains(User $managedUser, UserRole $newRole, bool $willBeActive): void
    {
        $removesAdministrativeAccess = $managedUser->role === UserRole::Admin
            && $managedUser->is_active
            && ($newRole !== UserRole::Admin || ! $willBeActive);

        if (! $removesAdministrativeAccess) {
            return;
        }

        $anotherAdministratorExists = User::query()
            ->whereKeyNot($managedUser->getKey())
            ->where('role', UserRole::Admin->value)
            ->where('is_active', true)
            ->lockForUpdate()
            ->exists();

        if (! $anotherAdministratorExists) {
            throw ValidationException::withMessages([
                'role' => ['Debe permanecer al menos un administrador activo.'],
            ]);
        }
    }
}
