<?php

namespace App\Policies;

use App\Models\InventoryDocument;
use App\Models\User;

class InventoryDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canOperateInventory();
    }

    public function view(User $user, InventoryDocument $document): bool
    {
        return $user->canOperateInventory();
    }

    public function create(User $user): bool
    {
        return $user->canOperateInventory();
    }

    public function update(User $user, InventoryDocument $document): bool
    {
        return $document->isDraft()
            && $user->canOperateInventory()
            && (! $document->type->requiresAdjustmentPermission() || $user->canAdjustInventory());
    }

    public function post(User $user, InventoryDocument $document): bool
    {
        return $this->update($user, $document);
    }

    public function delete(User $user, InventoryDocument $document): bool
    {
        return $this->update($user, $document);
    }
}
