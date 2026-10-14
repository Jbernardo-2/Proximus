<?php

namespace App;

enum CustomerVisitReason: string
{
    case BusinessClosed = 'business_closed';
    case OwnerAbsent = 'owner_absent';
    case HasStock = 'has_stock';
    case NoMoney = 'no_money';
    case NotInterested = 'not_interested';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::BusinessClosed => 'Negocio cerrado',
            self::OwnerAbsent => 'Encargado ausente',
            self::HasStock => 'Aún tiene producto',
            self::NoMoney => 'Sin dinero para comprar',
            self::NotInterested => 'No le interesa',
            self::Other => 'Otro motivo',
        };
    }
}
