<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Warehouse;

class WarehousePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canViewInventory();
    }

    public function view(User $user, Warehouse $warehouse): bool
    {
        return $user->canViewInventory();
    }

    public function create(User $user): bool
    {
        return $user->canConfigureInventory();
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        return $user->canConfigureInventory();
    }

    public function delete(User $user, Warehouse $warehouse): bool
    {
        return false;
    }
}
