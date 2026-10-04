<?php

namespace App;

enum OrderPriceSource: string
{
    case Presentation = 'presentation';
    case PriceTier = 'price_tier';
    case Override = 'override';

    public function label(): string
    {
        return match ($this) {
            self::Presentation => 'Precio normal',
            self::PriceTier => 'Precio por cantidad',
            self::Override => 'Precio autorizado',
        };
    }
}
