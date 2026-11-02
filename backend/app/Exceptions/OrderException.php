<?php

namespace App\Exceptions;

use App\Enums\HttpStatusCodeEnum;

class OrderException extends ApiException
{
    public static function transitionNotAllowed(): OrderException
    {
        return new self(__('messages.order_exceptions.transition_not_allowed'), HttpStatusCodeEnum::BAD_REQUEST->value);
    }

    public static function supplierMismatch(): OrderException
    {
        return new self(__('messages.order_exceptions.supplier_mismatch'), HttpStatusCodeEnum::BAD_REQUEST->value);
    }
}
