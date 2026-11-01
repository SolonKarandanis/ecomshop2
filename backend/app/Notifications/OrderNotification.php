<?php

namespace App\Notifications;

use App\Enums\NotificationEventTypeEnum;
use App\Support\OrderLinks;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Stores a translation key and its parameters rather than rendered text, so
 * NotificationData shows the message in the reader's language (#80). The event
 * type doubles as the key: lang/{locale}/order.php `notification.*`.
 */
class OrderNotification extends Notification implements ShouldBroadcast, ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly NotificationEventTypeEnum $eventType,
        private readonly int $orderId,
        private readonly int $userId,
        private readonly int $buyerId,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->payload($notifiable);
    }

    // Laravel builds every channel in the recipient's preferred locale, so a
    // live broadcast can carry the message already rendered.
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        $payload = $this->payload($notifiable);

        return new BroadcastMessage([
            ...$payload,
            'message' => __($payload['message_key'], $payload['message_params']),
        ]);
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('user.'.$this->userId);
    }

    private function payload(object $notifiable): array
    {
        return [
            'event_type' => $this->eventType->value,
            'order_id' => $this->orderId,
            'order_url' => OrderLinks::forRecipient($notifiable, $this->orderId, $this->buyerId),
            'message_key' => $this->eventType->value,
            'message_params' => ['order' => $this->orderId],
        ];
    }
}
