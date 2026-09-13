<?php

namespace App\Data;

use App\Models\Notification;
use Carbon\Carbon;

class NotificationData extends BaseData
{
    public function __construct(
        public string $id,
        public ?string $event_type,
        public ?int $order_id,
        public ?string $order_url,
        public ?string $message,
        public ?Carbon $read_at,
        public ?Carbon $created_at,
    ) {}

    public static function fromModel(Notification $notification): self
    {
        return new self(
            id: $notification->id,
            event_type: $notification->data['event_type'] ?? null,
            order_id: $notification->data['order_id'] ?? null,
            order_url: $notification->data['order_url'] ?? null,
            message: $notification->data['message'] ?? null,
            read_at: $notification->read_at,
            created_at: $notification->created_at,
        );
    }
}
