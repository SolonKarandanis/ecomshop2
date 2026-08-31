<?php

namespace App\Exceptions;

use App\Enums\HttpStatusCodeEnum;

class ProductNotFoundException extends \Exception
{
    public static function productNotFound(int $productId): ProductNotFoundException
    {
        return new self(__('messages.product_exceptions.not_found', ['id' => $productId]), HttpStatusCodeEnum::NOT_FOUND->value);
    }
}
