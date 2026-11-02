<?php

namespace App\Exceptions;

use App\Enums\HttpStatusCodeEnum;

class CartException extends ApiException
{
    public static function supplierMismatch(): CartException
    {
        return new self(__('messages.cart_exceptions.supplier_mismatch'), HttpStatusCodeEnum::BAD_REQUEST->value);
    }
}
