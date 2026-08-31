<?php

namespace App\Exceptions;

use App\Enums\HttpStatusCodeEnum;

class EmptyCartException extends \Exception
{
    public static function emptyCart(): EmptyCartException
    {
        return new self(__('messages.cart_exceptions.empty_cart'), HttpStatusCodeEnum::BAD_REQUEST->value);
    }
}
