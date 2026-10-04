<?php

namespace App;

enum InventoryDocumentStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Posted => 'Aplicado',
            self::Cancelled => 'Cancelado',
        };
    }
}
