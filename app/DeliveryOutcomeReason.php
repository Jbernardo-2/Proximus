<?php

namespace App;

enum DeliveryOutcomeReason: string
{
    case CustomerAbsent = 'customer_absent';
    case BusinessClosed = 'business_closed';
    case CustomerRejected = 'customer_rejected';
    case CannotPay = 'cannot_pay';
    case AddressNotFound = 'address_not_found';
    case StockShortage = 'stock_shortage';
    case DamagedGoods = 'damaged_goods';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CustomerAbsent => 'Cliente ausente',
            self::BusinessClosed => 'Negocio cerrado',
            self::CustomerRejected => 'Cliente rechazó el pedido',
            self::CannotPay => 'Cliente no pudo pagar',
            self::AddressNotFound => 'Dirección no encontrada',
            self::StockShortage => 'Faltante desde bodega',
            self::DamagedGoods => 'Producto dañado',
            self::Other => 'Otro motivo',
        };
    }
}
