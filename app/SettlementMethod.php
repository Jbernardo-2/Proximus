<?php

namespace App;

enum SettlementMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Card = 'card';
    case Check = 'check';
    case Other = 'other';
    case Credit = 'credit';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Efectivo',
            self::BankTransfer => 'Transferencia bancaria',
            self::Card => 'Tarjeta',
            self::Check => 'Cheque',
            self::Other => 'Otro',
            self::Credit => 'Crédito',
        };
    }

    public function isCredit(): bool
    {
        return $this === self::Credit;
    }

    public function requiresReference(): bool
    {
        return in_array($this, [self::Card, self::Check], true);
    }

    public function paymentMethod(): ?PaymentMethod
    {
        return $this->isCredit() ? null : PaymentMethod::from($this->value);
    }
}
