<?php

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'super_admin';
    case PlatformOwner = 'platform_owner';
    case Admin = 'admin';
    case Cashier = 'cashier';
    case Warehouse = 'warehouse';

    /**
     * Get the Indonesian label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Administrator',
            self::PlatformOwner => 'Platform Owner',
            self::Admin => 'Administrator',
            self::Cashier => 'Kasir',
            self::Warehouse => 'Gudang',
        };
    }
}
