<?php

namespace App\Payments;

use App\Models\Order;

class CashOnDeliveryPaymentHandler implements PaymentHandlerInterface
{
    public function process(Order $order, array $lineItems): string {
        return route('success');
    }
}
