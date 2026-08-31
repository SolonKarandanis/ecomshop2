<?php

namespace App\Enums;

enum PaymentMethodEnum:string
{
    case CASH_ON_DELIVERY = 'payment.method.cod';
    case STRIPE = 'payment.method.stripe';
    case PAYPAL = 'payment.method.paypal';

    public static function labels(): array
    {
        return [
            self::CASH_ON_DELIVERY->value => __('payment.method.cod'),
            self::STRIPE->value => __('payment.method.stripe'),
            self::PAYPAL->value => __('payment.method.paypal'),
        ];
    }
}
