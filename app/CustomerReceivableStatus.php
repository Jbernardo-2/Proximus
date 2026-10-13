<?php

namespace App;

enum CustomerReceivableStatus: string
{
    case Open = 'open';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Pendiente',
            self::PartiallyPaid => 'Abono parcial',
            self::Paid => 'Pagada',
            self::Voided => 'Anulada',
        };
    }

    public function isOutstanding(): bool
    {
        return in_array($this, [self::Open, self::PartiallyPaid], true);
    }
}
