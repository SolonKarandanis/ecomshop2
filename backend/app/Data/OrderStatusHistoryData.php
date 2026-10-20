<?php

namespace App\Data;

use App\Models\OrderStatusHistory;
use Illuminate\Support\Carbon;

/**
 * One entry of an Order's status history. Leaves out who made the change:
 * Buyers don't need to know which admin or Supplier it was. Admins see that in Filament.
 */
class OrderStatusHistoryData extends BaseData
{
    public function __construct(
        public string $order_status,
        public ?string $payment_status,
        public ?Carbon $created_at,
    ) {}

    public static function fromModel(OrderStatusHistory $entry): self
    {
        return new self(
            order_status: $entry->order_status,
            payment_status: $entry->payment_status,
            created_at: $entry->created_at,
        );
    }
}
