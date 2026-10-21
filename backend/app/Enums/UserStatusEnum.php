<?php

namespace App\Enums;

enum UserStatusEnum:string
{
    case ACTIVE = 'user.status.active';
    case INACTIVE = 'user.status.inactive';
    // Registered, not yet approved by an admin (Suppliers only).
    case PENDING = 'user.status.pending';

    public static function labels(): array
    {
        return [
            self::ACTIVE->value => __('user.status.active'),
            self::INACTIVE->value => __('user.status.inactive'),
            self::PENDING->value => __('user.status.pending'),
        ];
    }

    public static function colors(): array
    {
        return [
            'success' => self::ACTIVE->value,
            'danger'  => self::INACTIVE->value,
            'warning' => self::PENDING->value,
        ];
    }
}
