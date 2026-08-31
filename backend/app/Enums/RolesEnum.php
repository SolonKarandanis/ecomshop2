<?php

namespace App\Enums;

enum RolesEnum: string
{
    case ROLE_ADMIN = 'role.admin';
    case ROLE_BUYER = 'role.buyer';
    case ROLE_SUPPLIER = 'role.supplier';

    public static function labels(): array
    {
        return [
            self::ROLE_ADMIN->value => __('role.admin'),
            self::ROLE_BUYER->value => __('role.buyer'),
            self::ROLE_SUPPLIER->value => __('role.supplier'),
        ];
    }
}
