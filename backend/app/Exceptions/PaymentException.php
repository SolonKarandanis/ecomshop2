<?php

namespace App\Exceptions;

use App\Enums\HttpStatusCodeEnum;
use Throwable;

class PaymentException extends ApiException
{
    public static function createStipeSession(Throwable $previous): PaymentException
    {
        return new self(__('messages.payment_exceptions.create_stripe_session'), HttpStatusCodeEnum::BAD_GATEWAY->value, $previous);
    }

    public static function retrieveStipeSession(Throwable $previous): PaymentException
    {
        return new self(__('messages.payment_exceptions.retrieve_stripe_session'), HttpStatusCodeEnum::BAD_GATEWAY->value, $previous);
    }

    public static function unsupportedPaymentMethod(string $paymentMethod): PaymentException
    {
        return new self(__('messages.payment_exceptions.unsupported_payment_method', ['method' => $paymentMethod]), HttpStatusCodeEnum::BAD_REQUEST->value);
    }
}
