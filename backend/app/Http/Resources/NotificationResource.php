<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_type' => $this->data['event_type'] ?? null,
            'order_id' => $this->data['order_id'] ?? null,
            'order_url' => $this->data['order_url'] ?? null,
            'message' => $this->data['message'] ?? null,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }
}
