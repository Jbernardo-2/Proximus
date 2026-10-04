<?php

namespace App;

enum DeliveryOrderStatus: string
{
    case Pending = 'pending';
    case Loaded = 'loaded';
    case Delivered = 'delivered';
    case PartiallyDelivered = 'partially_delivered';
    case NotDelivered = 'not_delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Loaded => 'Cargado',
            self::Delivered => 'Entregado',
            self::PartiallyDelivered => 'Entrega parcial',
            self::NotDelivered => 'No entregado',
            self::Cancelled => 'Cancelado',
        };
    }

    public function isCompleted(): bool
    {
        return in_array($this, [self::Delivered, self::PartiallyDelivered, self::NotDelivered], true);
    }
}
