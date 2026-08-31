<?php

namespace App\Enums;

enum ProductVariationTypesEnum:string
{
    case Select = 'product.variation.type.select';
    case Radio = 'product.variation.type.radio';
    case Image = 'product.variation.type.image';

    public static function labels(): array
    {
        return [
            self::Select->value => __('product.variation.type.select'),
            self::Radio->value => __('product.variation.type.radio'),
            self::Image->value => __('product.variation.type.image'),
        ];
    }
}
