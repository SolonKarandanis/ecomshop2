<?php

namespace App\Payments;

use App\Models\Order;

class CashOnDeliveryPaymentHandler implements PaymentHandlerInterface
{
    /**
     * A COD Order has no Stripe session to finalize, so the Nuxt /success page
     * loads it by id instead of calling /api/success (ADR-0004).
     */
    public function process(Order $order, array $lineItems): string {
        return config('app.frontend_url').'/success?'.http_build_query(['order_id' => $order->id]);
    }
}
