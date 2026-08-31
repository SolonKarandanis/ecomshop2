<?php

namespace App\Payments;

use App\Models\Order;

interface PaymentHandlerInterface
{
    public function process(Order $order, array $lineItems): string; // returns redirect URL
}
