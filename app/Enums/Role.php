<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Cashier = 'cashier';
    case Warehouse = 'warehouse';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Cashier => 'Kasir',
            self::Warehouse => 'Gudang',
        };
    }
}
