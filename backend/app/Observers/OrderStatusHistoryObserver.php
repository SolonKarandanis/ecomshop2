<?php

namespace App\Observers;

use App\Models\Order;

/**
 * Records every change to an Order's order status or payment status, whichever
 * path made it (checkout, Stripe redirect and webhook, Supplier actions,
 * Filament). Unlike OrderObserver it runs inside the surrounding transaction,
 * so an entry is written or rolled back together with the change it records.
 */
class OrderStatusHistoryObserver
{
    public function created(Order $order): void
    {
        $this->record($order);
    }

    public function updated(Order $order): void
    {
        if ($order->wasChanged(['order_status', 'payment_status'])) {
            $this->record($order);
        }
    }

    private function record(Order $order): void
    {
        $order->statusHistory()->create([
            'order_status' => $order->order_status,
            'payment_status' => $order->payment_status,
            // Null outside a request with a logged-in user, e.g. the Stripe webhook.
            'changed_by' => auth()->id(),
        ]);
    }
}
