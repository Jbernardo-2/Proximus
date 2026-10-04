<?php

namespace App;

enum UserRole: string
{
    case Admin = 'admin';
    case Supervisor = 'supervisor';
    case Preventista = 'preventista';
    case Bodeguero = 'bodeguero';
    case Repartidor = 'repartidor';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Supervisor => 'Supervisor',
            self::Preventista => 'Preventista',
            self::Bodeguero => 'Bodeguero',
            self::Repartidor => 'Repartidor',
        };
    }

    public function canManageCatalog(): bool
    {
        return in_array($this, [self::Admin, self::Supervisor, self::Bodeguero], true);
    }

    public function canAccessPanel(): bool
    {
        return $this !== self::Repartidor;
    }

    public function canManageCustomers(): bool
    {
        return in_array($this, [self::Admin, self::Supervisor, self::Preventista], true);
    }

    public function canManageRoutes(): bool
    {
        return in_array($this, [self::Admin, self::Supervisor], true);
    }

    public function canViewRoutes(): bool
    {
        return in_array($this, [self::Admin, self::Supervisor, self::Preventista], true);
    }

    public function canViewOrders(): bool
    {
        return in_array($this, [self::Admin, self::Supervisor, self::Preventista, self::Bodeguero], true);
    }

    public function canManageOrders(): bool
    {
        return in_array($this, [self::Admin, self::Supervisor, self::Preventista], true);
    }

    public function canOverrideOrderPrices(): bool
    {
        return in_array($this, [self::Admin, self::Supervisor], true);
    }

    public function canManageOrderLifecycle(): bool
    {
        return in_array($this, [self::Admin, self::Supervisor], true);
    }

    public function canViewInventory(): bool
    {
        return in_array($this, [self::Admin, self::Supervisor, self::Preventista, self::Bodeguero], true);
    }

    public function canOperateInventory(): bool
    {
        return in_array($this, [self::Admin, self::Supervisor, self::Bodeguero], true);
    }

    public function canAdjustInventory(): bool
    {
        return in_array($this, [self::Admin, self::Supervisor], true);
    }

    public function canConfigureInventory(): bool
    {
        return in_array($this, [self::Admin, self::Supervisor], true);
    }

    public function canManageUsers(): bool
    {
        return $this === self::Admin;
    }

    /**
     * @return list<string>
     */
    public function apiAbilities(): array
    {
        $abilities = [];

        if ($this->canManageCatalog()) {
            $abilities[] = 'catalog:manage';
        }

        if ($this->canManageCustomers()) {
            $abilities[] = 'customers:manage';
        }

        if ($this->canViewRoutes()) {
            $abilities[] = 'routes:view';
        }

        if ($this->canManageRoutes()) {
            $abilities[] = 'routes:manage';
        }

        if ($this->canViewOrders()) {
            $abilities[] = 'orders:view';
        }

        if ($this->canManageOrders()) {
            $abilities[] = 'orders:manage';
        }

        if ($this->canOverrideOrderPrices()) {
            $abilities[] = 'orders:override';
        }

        if ($this->canManageOrderLifecycle()) {
            $abilities[] = 'orders:lifecycle';
        }

        if ($this->canViewInventory()) {
            $abilities[] = 'inventory:view';
        }

        if ($this->canOperateInventory()) {
            $abilities[] = 'inventory:operate';
        }

        if ($this->canAdjustInventory()) {
            $abilities[] = 'inventory:adjust';
        }

        if ($this->canConfigureInventory()) {
            $abilities[] = 'inventory:configure';
        }

        if ($this->canManageUsers()) {
            $abilities[] = 'users:manage';
        }

        return $abilities;
    }
}
