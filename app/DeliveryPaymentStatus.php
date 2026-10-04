<?php

namespace App;

enum DeliveryPaymentStatus: string
{
    case Active = 'active';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Vigente',
            self::Voided => 'Anulado',
        };
    }
}
