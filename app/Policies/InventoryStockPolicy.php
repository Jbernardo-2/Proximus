<?php

namespace App\Policies;

use App\Models\InventoryStock;
use App\Models\User;

class InventoryStockPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canViewInventory();
    }

    public function view(User $user, InventoryStock $inventoryStock): bool
    {
        return $user->canViewInventory();
    }

    public function update(User $user, InventoryStock $inventoryStock): bool
    {
        return $user->canConfigureInventory();
    }
}
