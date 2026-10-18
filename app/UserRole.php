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

    /** What the role does, to choose it without doubts. */
    public function description(): string
    {
        return match ($this) {
            self::Admin => 'Todo el sistema: usuarios, empresa, configuración y reportes.',
            self::Supervisor => 'Supervisa ventas, reparto y bodega; liquida jornadas, ve el crédito y los reportes.',
            self::Preventista => 'Visita clientes de su ruta, toma pedidos y cobra abonos desde el teléfono.',
            self::Bodeguero => 'Prepara y carga jornadas, cuenta existencias y registra entradas de mercadería.',
            self::Repartidor => 'Entrega pedidos, cobra y entrega el efectivo de su jornada desde el teléfono.',
        };
    }

    public function canManageCatalog(): bool
    {
        return in_array($this, [self::Admin, self::Supervisor, self::Bodeguero], true);
    }

    public function canAccessPanel(): bool
    {
        return true;
    }

    public function canManageCustomers(): bool
    {
        return in_array($this, [self::Admin, self::Supervisor, self::Preventista], true);
    }

    public function canViewCustomerCredit(): bool
    {
        return in_array($this, [self::Admin, self::Supervisor, self::Preventista], true);
    }

    public function canConfigureCustomerCredit(): bool
    {
        return in_array($this, [self::Admin, self::Supervisor], true);
    }

    /** The driver collects old debts at the door as well; which customers, CustomerPolicy::collect decides. */
    public function canRecordCustomerPayments(): bool
    {
        return in_array($this, [self::Admin, self::Supervisor, self::Preventista, self::Repartidor], true);
    }

    public function canReverseCustomerPayments(): bool
    {
        return in_array($this, [self::Admin, self::Supervisor], true);
    }

    public function canOverrideCustomerCredit(): bool
    {
        return in_array($this, [self::Admin, self::Supervisor], true);
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

    public function canViewDeliveries(): bool
    {
        return in_array($this, [self::Admin, self::Supervisor, self::Bodeguero, self::Repartidor], true);
    }

    public function canViewReports(): bool
    {
        return in_array($this, [self::Admin, self::Supervisor], true);
    }

    public function canViewRouteReports(): bool
    {
        return true;
    }

    public function canManageDeliveries(): bool
    {
        return in_array($this, [self::Admin, self::Supervisor, self::Bodeguero], true);
    }

    public function canPrepareDeliveries(): bool
    {
        return in_array($this, [self::Admin, self::Supervisor, self::Bodeguero], true);
    }

    public function canExecuteDeliveries(): bool
    {
        return in_array($this, [self::Admin, self::Supervisor, self::Repartidor], true);
    }

    public function canSettleDeliveries(): bool
    {
        return in_array($this, [self::Admin, self::Supervisor], true);
    }

    public function canManageVehicles(): bool
    {
        return in_array($this, [self::Admin, self::Supervisor], true);
    }

    public function canManageUsers(): bool
    {
        return $this === self::Admin;
    }

    public function canManageCompanyProfile(): bool
    {
        return $this === self::Admin;
    }

    /**
     * @return list<string>
     */
    public function apiAbilities(): array
    {
        $abilities = [];

        // On the phone only supervisors and admins manage products; the keeper keeps the
        // catalog on the web, which goes by the session and not by these token abilities.
        if ($this->canManageCatalog() && $this !== self::Bodeguero) {
            $abilities[] = 'catalog:manage';
        }

        if ($this->canManageCustomers()) {
            $abilities[] = 'customers:manage';
        }

        if ($this->canViewCustomerCredit()) {
            $abilities[] = 'credits:view';
        }

        if ($this->canConfigureCustomerCredit()) {
            $abilities[] = 'credits:configure';
        }

        if ($this->canRecordCustomerPayments()) {
            $abilities[] = 'payments:record';
        }

        if ($this->canReverseCustomerPayments()) {
            $abilities[] = 'payments:reverse';
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

        if ($this->canViewDeliveries()) {
            $abilities[] = 'deliveries:view';
        }

        if ($this->canManageDeliveries()) {
            $abilities[] = 'deliveries:manage';
        }

        if ($this->canPrepareDeliveries()) {
            $abilities[] = 'deliveries:prepare';
        }

        if ($this->canExecuteDeliveries()) {
            $abilities[] = 'deliveries:execute';
        }

        if ($this->canSettleDeliveries()) {
            $abilities[] = 'deliveries:settle';
        }

        if ($this->canManageVehicles()) {
            $abilities[] = 'vehicles:manage';
        }

        if ($this->canManageUsers()) {
            $abilities[] = 'users:manage';
        }

        if ($this->canViewReports()) {
            $abilities[] = 'reports:view';
        }

        if ($this->canViewRouteReports()) {
            $abilities[] = 'route-reports:view';
        }

        if ($this->canManageCompanyProfile()) {
            $abilities[] = 'company:manage';
        }

        return $abilities;
    }
}
