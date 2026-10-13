<?php

namespace App;

enum CustomerAccountEntryType: string
{
    case Sale = 'sale';
    case OpeningBalance = 'opening_balance';
    case DeliveryPayment = 'delivery_payment';
    case CustomerPayment = 'customer_payment';
    case Reversal = 'reversal';

    public function label(): string
    {
        return match ($this) {
            self::Sale => 'Compra',
            self::OpeningBalance => 'Saldo inicial',
            self::DeliveryPayment => 'Pago en entrega',
            self::CustomerPayment => 'Abono',
            self::Reversal => 'Reversión',
        };
    }
}
