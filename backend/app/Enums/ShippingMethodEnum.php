<?php

namespace App\Enums;

enum ShippingMethodEnum:string
{
    case NONE = 'order.shipping.method.none';

    public static function labels(): array
    {
        return [
            self::NONE->value => __('order.shipping.method.none'),
        ];
    }
}
