<?php

namespace App;

enum VisitLocationStatus: string
{
    case Captured = 'captured';
    case Denied = 'denied';
    case Unavailable = 'unavailable';

    public function label(): string
    {
        return match ($this) {
            self::Captured => 'Con ubicación',
            self::Denied => 'Permiso negado',
            self::Unavailable => 'Sin ubicación',
        };
    }
}
