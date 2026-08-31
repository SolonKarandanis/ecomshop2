<?php

namespace App\Enums;

enum NotificationEventTypeEnum: string
{
    case ORDER_CREATED = 'order.notification.created';
    case ORDER_PAYMENT_CONFIRMED = 'order.notification.payment_confirmed';
    case ORDER_PAYMENT_FAILED = 'order.notification.payment_failed';
    case ORDER_SHIPPED = 'order.notification.shipped';
    case ORDER_DELIVERED = 'order.notification.delivered';
    case ORDER_CANCELLED = 'order.notification.cancelled';
    case ORDER_READY_FOR_SUPPLIER = 'order.notification.ready_for_supplier';
}
