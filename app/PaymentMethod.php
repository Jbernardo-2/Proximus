<?php

namespace App;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Card = 'card';
    case Check = 'check';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Efectivo',
            self::BankTransfer => 'Transferencia',
            self::Card => 'Tarjeta',
            self::Check => 'Cheque',
            self::Other => 'Otro',
        };
    }

    public function requiresReference(): bool
    {
        return in_array($this, [self::BankTransfer, self::Card, self::Check], true);
    }
}
