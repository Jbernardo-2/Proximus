<?php

namespace App\Models;

use App\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'role', 'is_active', 'password', 'email_verified_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public function salesRoutes(): HasMany
    {
        return $this->hasMany(SalesRoute::class, 'salesperson_id');
    }

    public function deliveryRoutes(): HasMany
    {
        return $this->hasMany(SalesRoute::class, 'driver_id');
    }

    public function createdInventoryDocuments(): HasMany
    {
        return $this->hasMany(InventoryDocument::class, 'created_by');
    }

    public function createdInventoryCounts(): HasMany
    {
        return $this->hasMany(InventoryCount::class, 'created_by');
    }

    public function canAccessPanel(): bool
    {
        return $this->is_active && $this->role->canAccessPanel();
    }

    public function canManageCatalog(): bool
    {
        return $this->is_active && $this->role->canManageCatalog();
    }

    public function canManageUsers(): bool
    {
        return $this->is_active && $this->role->canManageUsers();
    }

    public function canManageCustomers(): bool
    {
        return $this->is_active && $this->role->canManageCustomers();
    }

    public function canManageRoutes(): bool
    {
        return $this->is_active && $this->role->canManageRoutes();
    }

    public function canViewRoutes(): bool
    {
        return $this->is_active && $this->role->canViewRoutes();
    }

    public function canViewOrders(): bool
    {
        return $this->is_active && $this->role->canViewOrders();
    }

    public function canManageOrders(): bool
    {
        return $this->is_active && $this->role->canManageOrders();
    }

    public function canOverrideOrderPrices(): bool
    {
        return $this->is_active && $this->role->canOverrideOrderPrices();
    }

    public function canManageOrderLifecycle(): bool
    {
        return $this->is_active && $this->role->canManageOrderLifecycle();
    }

    public function canViewInventory(): bool
    {
        return $this->is_active && $this->role->canViewInventory();
    }

    public function canOperateInventory(): bool
    {
        return $this->is_active && $this->role->canOperateInventory();
    }

    public function canAdjustInventory(): bool
    {
        return $this->is_active && $this->role->canAdjustInventory();
    }

    public function canConfigureInventory(): bool
    {
        return $this->is_active && $this->role->canConfigureInventory();
    }

    /**
     * @return list<string>
     */
    public function apiAbilities(): array
    {
        return $this->is_active ? $this->role->apiAbilities() : [];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }
}
