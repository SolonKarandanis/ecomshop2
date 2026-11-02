<?php

namespace App\Services;

use App\Enums\NotificationEventTypeEnum;
use App\Mail\OrderPlaced;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderNotification;
use App\Notifications\SupplierApprovedNotification;
use App\Notifications\SupplierAwaitingApprovalNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class NotificationHandlerService
{
    public function orderCreated(Order $order): void
    {
        $user = $order->user;
        $user->notify(new OrderNotification(
            NotificationEventTypeEnum::ORDER_CREATED,
            $order->id,
            $user->id,
            $order->user_id,
        ));
        // The Order is already placed: a mail that can't be queued is reported,
        // with its stack trace, but doesn't fail the checkout.
        try {
            Mail::to($user)->send(new OrderPlaced($order));
        } catch (\Exception $e) {
            report($e);
        }
    }

    public function orderPaymentConfirmed(Order $order): void
    {
        $buyer = $order->user;
        $order->user->notify(new OrderNotification(
            NotificationEventTypeEnum::ORDER_PAYMENT_CONFIRMED,
            $order->id,
            $buyer->id,
            $order->user_id,
        ));
    }

    public function orderPaymentFailed(Order $order): void
    {
        $buyer = $order->user;
        $buyer->notify(new OrderNotification(
            NotificationEventTypeEnum::ORDER_PAYMENT_FAILED,
            $order->id,
            $buyer->id,
            $order->user_id,
        ));
    }

    public function orderShipped(Order $order): void
    {
        $buyer = $order->user;
        $buyer->notify(new OrderNotification(
            NotificationEventTypeEnum::ORDER_SHIPPED,
            $order->id,
            $buyer->id,
            $order->user_id,
        ));
    }

    public function orderDelivered(Order $order): void
    {
        $buyer = $order->user;
        $buyer->notify(new OrderNotification(
            NotificationEventTypeEnum::ORDER_DELIVERED,
            $order->id,
            $buyer->id,
            $order->user_id,
        ));
    }

    public function orderReadyForSupplier(Order $order): void
    {
        $supplier = $order->supplier;
        $supplier->notify(new OrderNotification(
            NotificationEventTypeEnum::ORDER_READY_FOR_SUPPLIER,
            $order->id,
            $supplier->id,
            $order->user_id,
        ));
    }

    public function orderCancelled(Order $order): void
    {
        $buyer = $order->user;
        $buyer->notify(new OrderNotification(
            NotificationEventTypeEnum::ORDER_CANCELLED,
            $order->id,
            $buyer->id,
            $order->user_id,
        ));
    }

    /**
     * @param  Collection<int, User>  $admins
     */
    public function supplierAwaitingApproval(User $supplier, Collection $admins): void
    {
        Notification::send($admins, new SupplierAwaitingApprovalNotification($supplier->id, $supplier->name));
    }

    public function supplierApproved(User $supplier): void
    {
        $supplier->notify(new SupplierApprovedNotification);
    }
}
