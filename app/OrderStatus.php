<?php

namespace App;

enum OrderStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Assigned = 'assigned';
    case Loaded = 'loaded';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case PartiallyDelivered = 'partially_delivered';
    case NotDelivered = 'not_delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Confirmed => 'Confirmado',
            self::Assigned => 'Asignado a reparto',
            self::Loaded => 'Cargado',
            self::InTransit => 'En ruta',
            self::Delivered => 'Entregado',
            self::PartiallyDelivered => 'Entrega parcial',
            self::NotDelivered => 'No entregado',
            self::Cancelled => 'Cancelado',
        };
    }
}
