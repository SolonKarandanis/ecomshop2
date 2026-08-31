<?php

namespace App\Notifications;

use App\Enums\NotificationEventTypeEnum;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class OrderNotification extends Notification implements ShouldQueue, ShouldBroadcast
{
    use Queueable;

    public function __construct(
        private readonly NotificationEventTypeEnum $eventType,
        private readonly int $orderId,
        private readonly string $message,
        private readonly int $userId,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->payload();
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->payload());
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('user.' . $this->userId);
    }

    private function payload(): array
    {
        return [
            'event_type' => $this->eventType->value,
            'order_id'   => $this->orderId,
            'order_url'  => route('my-orders.detail', $this->orderId),
            'message'    => $this->message,
        ];
    }
}
