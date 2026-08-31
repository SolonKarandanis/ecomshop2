<?php

namespace App\Enums;

enum UserStatusEnum:string
{
    case ACTIVE = 'user.status.active';
    case INACTIVE = 'user.status.inactive';

    public static function labels(): array
    {
        return [
            self::ACTIVE->value => __('user.status.active'),
            self::INACTIVE->value => __('user.status.inactive'),
        ];
    }

    public static function colors(): array
    {
        return [
            'success' => self::ACTIVE->value,
            'danger'  => self::INACTIVE->value,
        ];
    }
}
