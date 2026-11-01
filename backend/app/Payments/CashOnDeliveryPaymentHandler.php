<?php

namespace App\Payments;

use App\Models\Order;
use App\Support\FrontendUrl;
use Illuminate\Support\Facades\App;

class CashOnDeliveryPaymentHandler implements PaymentHandlerInterface
{
    /**
     * A COD Order has no Stripe session to finalize, so the Nuxt /success page
     * loads it by id instead of calling /api/success (ADR-0004). It opens in
     * the language the Buyer is checking out in.
     */
    public function process(Order $order, array $lineItems): string
    {
        return FrontendUrl::to('/success?'.http_build_query(['order_id' => $order->id]), App::getLocale());
    }
}
