<?php

namespace App\Exceptions;

use App\Enums\HttpStatusCodeEnum;

class OrderException extends \Exception
{
    public static function createOrder(): OrderException
    {
        return new self(__('messages.order_exceptions.create_order'), HttpStatusCodeEnum::INTERNAL_SERVER_ERROR->value);
    }

    public static function checkout(): OrderException
    {
        return new self(__('messages.order_exceptions.checkout'), HttpStatusCodeEnum::BAD_REQUEST->value);
    }

    public static function stripeProcessing(): OrderException
    {
        return new self(__('messages.order_exceptions.stripe_processing'), HttpStatusCodeEnum::BAD_REQUEST->value);
    }

    public static function supplierActionNotAllowed(): OrderException
    {
        return new self(__('messages.order_exceptions.supplier_action_not_allowed'), HttpStatusCodeEnum::BAD_REQUEST->value);
    }

    public static function supplierMismatch(): OrderException
    {
        return new self(__('messages.order_exceptions.supplier_mismatch'), HttpStatusCodeEnum::BAD_REQUEST->value);
    }
}
