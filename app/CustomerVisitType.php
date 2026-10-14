<?php

namespace App;

enum CustomerVisitType: string
{
    case OrderTaken = 'order_taken';
    case NoSale = 'no_sale';
    case Delivery = 'delivery';
    case DeliveryPayment = 'delivery_payment';
    case AccountPayment = 'account_payment';

    public function label(): string
    {
        return match ($this) {
            self::OrderTaken => 'Pedido tomado',
            self::NoSale => 'Visita sin pedido',
            self::Delivery => 'Entrega',
            self::DeliveryPayment => 'Cobro en entrega',
            self::AccountPayment => 'Abono a cuenta',
        };
    }
}
