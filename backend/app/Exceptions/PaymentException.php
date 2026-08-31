<?php

namespace App\Exceptions;

use App\Enums\HttpStatusCodeEnum;

class PaymentException extends \Exception
{
    public static function createStipeSession(): PaymentException
    {
        return new self(__('messages.payment_exceptions.create_stripe_session'), HttpStatusCodeEnum::BAD_GATEWAY->value);
    }

    public static function retrieveStipeSession(): PaymentException
    {
        return new self(__('messages.payment_exceptions.retrieve_stripe_session'), HttpStatusCodeEnum::BAD_GATEWAY->value);
    }

    public static function unsupportedPaymentMethod(string $paymentMethod): PaymentException
    {
        return new self(__('messages.payment_exceptions.unsupported_payment_method', ['method' => $paymentMethod]), HttpStatusCodeEnum::BAD_REQUEST->value);
    }
}
