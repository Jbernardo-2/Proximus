<?php

namespace App;

enum InventoryReservationStatus: string
{
    case Active = 'active';
    case Released = 'released';
    case Fulfilled = 'fulfilled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activa',
            self::Released => 'Liberada',
            self::Fulfilled => 'Despachada',
        };
    }
}
