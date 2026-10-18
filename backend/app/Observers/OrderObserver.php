<?php

namespace App\Observers;

use App\Enums\OrderStatusEnum;
use App\Models\Order;
use App\Services\NotificationHandlerService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Sends the order-status Notifications for every path that changes an Order's
 * status (Stripe redirect and webhook, Supplier actions, Filament), so each one
 * is sent once. Runs after the surrounding transaction commits, so a rolled-back
 * change never notifies anyone.
 */
class OrderObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly NotificationHandlerService $notificationService,
    ) {}

    public function updated(Order $order): void
    {
        if (! $order->wasChanged('order_status')) {
            return;
        }

        match ($order->order_status) {
            OrderStatusEnum::Paid->value      => $this->orderPaid($order),
            OrderStatusEnum::Shipped->value   => $this->notificationService->orderShipped($order),
            OrderStatusEnum::Delivered->value => $this->notificationService->orderDelivered($order),
            OrderStatusEnum::Cancelled->value => $this->notificationService->orderCancelled($order),
            default                           => null,
        };
    }

    /**
     * With SUPPLIERS_ENABLED=false the Order's Supplier is the admin who owns its
     * products, so the admin is told a paid Order needs shipping.
     */
    private function orderPaid(Order $order): void
    {
        $this->notificationService->orderPaymentConfirmed($order);
        $this->notificationService->orderReadyForSupplier($order);
    }
}
