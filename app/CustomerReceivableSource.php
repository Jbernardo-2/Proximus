<?php

namespace App;

enum CustomerReceivableSource: string
{
    case Sale = 'sale';
    case OpeningBalance = 'opening_balance';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Sale => 'Venta',
            self::OpeningBalance => 'Saldo inicial',
            self::Adjustment => 'Ajuste',
        };
    }
}
