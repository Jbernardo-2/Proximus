<?php

namespace App;

enum DeliveryRunStatus: string
{
    case Draft = 'draft';
    case Preparing = 'preparing';
    case Loaded = 'loaded';
    case InTransit = 'in_transit';
    case AwaitingSettlement = 'awaiting_settlement';
    case Settled = 'settled';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Preparing => 'En preparación',
            self::Loaded => 'Cargada',
            self::InTransit => 'En ruta',
            self::AwaitingSettlement => 'Por liquidar',
            self::Settled => 'Liquidada',
            self::Cancelled => 'Cancelada',
        };
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Settled, self::Cancelled], true);
    }
}
