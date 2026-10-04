<?php

namespace App\Policies;

use App\Models\SalesRoute;
use App\Models\User;
use App\UserRole;

class SalesRoutePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->canViewRoutes();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SalesRoute $salesRoute): bool
    {
        if (! $user->canViewRoutes()) {
            return false;
        }

        return in_array($user->role, [UserRole::Admin, UserRole::Supervisor], true)
            || ($user->role === UserRole::Preventista && $salesRoute->salesperson_id === $user->id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->canManageRoutes();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SalesRoute $salesRoute): bool
    {
        return $user->canManageRoutes();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SalesRoute $salesRoute): bool
    {
        return $user->canManageRoutes();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, SalesRoute $salesRoute): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, SalesRoute $salesRoute): bool
    {
        return false;
    }
}
