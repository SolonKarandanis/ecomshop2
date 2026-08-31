<?php

namespace App\Enums;

enum AttributeValueMethodEnum:string
{
    case FIXED = 'attribute.value.method.fixed';
    case PERCENT = 'attribute.value.method.percent';

    public static function labels(): array
    {
        return [
            self::FIXED->value => __('attribute.value.method.fixed'),
            self::PERCENT->value => __('attribute.value.method.percent'),
        ];
    }
}
