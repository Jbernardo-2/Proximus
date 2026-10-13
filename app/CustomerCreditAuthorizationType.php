<?php

namespace App;

enum CustomerCreditAuthorizationType: string
{
    case OrderConfirmation = 'order_confirmation';
    case DeliverySettlement = 'delivery_settlement';

    public function label(): string
    {
        return match ($this) {
            self::OrderConfirmation => 'Confirmación de pedido',
            self::DeliverySettlement => 'Cierre de entrega',
        };
    }
}
