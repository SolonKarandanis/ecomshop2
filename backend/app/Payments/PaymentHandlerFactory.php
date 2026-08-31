<?php

namespace App\Payments;

use App\Enums\PaymentMethodEnum;
use App\Exceptions\PaymentException;

class PaymentHandlerFactory
{
    /**
     * @throws PaymentException
     */
    public function make(string $paymentMethod): PaymentHandlerInterface {
        return match($paymentMethod) {
            PaymentMethodEnum::STRIPE->value         => app(StripePaymentHandler::class),
            PaymentMethodEnum::CASH_ON_DELIVERY->value => app(CashOnDeliveryPaymentHandler::class),
            default => throw PaymentException::unsupportedPaymentMethod($paymentMethod),
        };
    }
}
