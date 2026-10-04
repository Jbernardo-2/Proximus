<?php

namespace App\Policies;

use App\Models\InventoryCount;
use App\Models\User;

class InventoryCountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canOperateInventory();
    }

    public function view(User $user, InventoryCount $inventoryCount): bool
    {
        return $user->canOperateInventory();
    }

    public function create(User $user): bool
    {
        return $user->canOperateInventory();
    }

    public function update(User $user, InventoryCount $inventoryCount): bool
    {
        return $inventoryCount->isDraft() && $user->canOperateInventory();
    }

    public function post(User $user, InventoryCount $inventoryCount): bool
    {
        return $this->update($user, $inventoryCount);
    }

    public function delete(User $user, InventoryCount $inventoryCount): bool
    {
        return $this->update($user, $inventoryCount);
    }
}
