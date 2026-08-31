<?php

namespace App\Enums;

enum OrderStatusEnum: string
{
    case Draft = 'order.status.draft';
    case Paid = 'order.status.paid';
    case Shipped = 'order.status.shipped';
    case Delivered = 'order.status.delivered';
    case Cancelled = 'order.status.cancelled';

    public static function labels(): array
    {
        return [
            self::Draft->value => __('order.status.draft'),
            self::Paid->value => __('order.status.paid'),
            self::Shipped->value => __('order.status.shipped'),
            self::Delivered->value => __('order.status.delivered'),
            self::Cancelled->value => __('order.status.cancelled'),
        ];
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Delivered, self::Cancelled => true,
            default => false,
        };
    }
}
