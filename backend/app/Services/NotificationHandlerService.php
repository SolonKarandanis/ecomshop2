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
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationHandlerService
{

    public function orderCreated(Order $order):void{
        $user = $order->user;
        $user->notify(new OrderNotification(
            NotificationEventTypeEnum::ORDER_CREATED,
            $order->id,
            "Your order #{$order->id} has been placed successfully.",
            $user->id,
            $order->user_id,
        ));
        try {
            Mail::to($user)->send(new OrderPlaced($order));
        } catch (\Exception $e) {
            Log::error('NotificationHandlerService mail sending failed: ' . $e->getMessage());
        }
    }

    public function orderPaymentConfirmed(Order $order):void{
        $buyer = $order->user;
        $order->user->notify(new OrderNotification(
            NotificationEventTypeEnum::ORDER_PAYMENT_CONFIRMED,
            $order->id,
            "Payment for order #{$order->id} has been confirmed.",
            $buyer->id,
            $order->user_id,
        ));
    }

    public function orderPaymentFailed(Order $order):void{
        $buyer = $order->user;
        $buyer->notify(new OrderNotification(
            NotificationEventTypeEnum::ORDER_PAYMENT_FAILED,
            $order->id,
            "Payment for order #{$order->id} failed. Please check your payment details.",
            $buyer->id,
            $order->user_id,
        ));
    }

    public function orderShipped(Order $order):void{
        $buyer = $order->user;
        $buyer->notify(new OrderNotification(
            NotificationEventTypeEnum::ORDER_SHIPPED,
            $order->id,
            "Your order #{$order->id} has been shipped.",
            $buyer->id,
            $order->user_id,
        ));
    }

    public function orderDelivered(Order $order):void{
        $buyer = $order->user;
        $buyer->notify(new OrderNotification(
            NotificationEventTypeEnum::ORDER_DELIVERED,
            $order->id,
            "Your order #{$order->id} has been delivered.",
            $buyer->id,
            $order->user_id,
        ));
    }

    public function orderReadyForSupplier(Order $order):void{
        $supplier = $order->supplier;
        $supplier->notify(new OrderNotification(
            NotificationEventTypeEnum::ORDER_READY_FOR_SUPPLIER,
            $order->id,
            "You have a new order to fulfill: #{$order->id}.",
            $supplier->id,
            $order->user_id,
        ));
    }

    public function orderCancelled(Order $order):void{
        $buyer = $order->user;
        $buyer->notify(new OrderNotification(
            NotificationEventTypeEnum::ORDER_CANCELLED,
            $order->id,
            "Your order #{$order->id} has been cancelled.",
            $buyer->id,
            $order->user_id,
        ));
    }

    /**
     * @param  Collection<int, User>  $admins
     */
    public function supplierAwaitingApproval(User $supplier, Collection $admins):void{
        Notification::send($admins, new SupplierAwaitingApprovalNotification($supplier->id, $supplier->name));
    }

    public function supplierApproved(User $supplier):void{
        $supplier->notify(new SupplierApprovedNotification());
    }
}
