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

        if ($this->canManageUsers()) {
            $abilities[] = 'users:manage';
        }

        return $abilities;
    }
}
