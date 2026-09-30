<?php

namespace App\Enums;

enum UserType: string
{
    case Customer = 'customer';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'زبون',
            self::Admin => 'مدير',
        };
    }
}
