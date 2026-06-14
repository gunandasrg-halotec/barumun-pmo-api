<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case PLANNING   = 'PLANNING';
    case ACTIVE     = 'ACTIVE';
    case ON_HOLD    = 'ON_HOLD';
    case COMPLETED  = 'COMPLETED';
    case CANCELLED  = 'CANCELLED';

    public function label(): string
    {
        return match($this) {
            self::PLANNING  => 'Planning',
            self::ACTIVE    => 'Active',
            self::ON_HOLD   => 'On Hold',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
