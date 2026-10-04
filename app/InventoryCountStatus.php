<?php

namespace App;

enum InventoryCountStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'En conteo',
            self::Posted => 'Aplicado',
            self::Cancelled => 'Cancelado',
        };
    }
}
