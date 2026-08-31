<?php

namespace App\Observers;

use App\Enums\OrderStatusEnum;
use App\Models\Order;
use App\Services\NotificationHandlerService;

class OrderObserver
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
            OrderStatusEnum::Shipped->value   => $this->notificationService->orderShipped($order),
            OrderStatusEnum::Delivered->value => $this->notificationService->orderDelivered($order),
            OrderStatusEnum::Cancelled->value => $this->notificationService->orderCancelled($order),
            default                           => null,
        };
    }
}
