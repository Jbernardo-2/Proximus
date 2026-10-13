<?php

namespace App;

enum CustomerCreditStatus: string
{
    case Disabled = 'disabled';
    case Active = 'active';
    case Suspended = 'suspended';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Disabled => 'Sin crédito',
            self::Active => 'Crédito activo',
            self::Suspended => 'Suspendido',
            self::Blocked => 'Bloqueado',
        };
    }

    public function allowsNewCredit(): bool
    {
        return $this === self::Active;
    }
}
