<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Warehouse = 'warehouse';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Warehouse => 'Gudang',
        };
    }
}
