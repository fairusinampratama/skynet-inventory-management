<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
        };
    }
}
