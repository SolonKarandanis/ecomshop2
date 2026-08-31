<?php

namespace App\Enums;

enum ProductStatusEnum:string
{
    case Draft = 'product.status.draft';
    case Published = 'product.status.published';

    public static function labels():array{
        return [
            self::Draft->value => __('product.status.draft'),
            self::Published->value => __('product.status.published'),
        ];
    }

    public static function colors():array{
        return [
            'gray' => self::Draft->value,
            'success' => self::Published->value,
        ];
    }
}
