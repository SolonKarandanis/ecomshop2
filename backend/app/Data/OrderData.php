<?php

namespace App\Data;

use App\Models\Order;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Lazy;

class OrderData extends BaseData
{
    public function __construct(
        public int $id,
        public string $order_status,
        public ?string $payment_status,
        public ?float $grand_total,
        public ?string $currency,
        public ?string $shipping_method,
        public ?float $shipping_amount,
        public ?string $notes,
        public ?Carbon $created_at,
        public Lazy|int|null $supplier_id,
        public Lazy|string|null $payment_method,
        public Lazy|AddressData|null $address,
        /** @var Lazy|DataCollection<int, OrderItemData> */
        public Lazy|DataCollection $items,
        // Only on single-Order responses, which load it; list endpoints don't.
        /** @var Lazy|DataCollection<int, OrderStatusHistoryData> */
        public Lazy|DataCollection $status_history,
    ) {}

    public static function fromModel(Order $order): self
    {
        $viewer = auth()->user();

        return new self(
            id: $order->id,
            order_status: $order->order_status,
            payment_status: $order->payment_status,
            grand_total: $order->grand_total?->toFloat(),
            currency: $order->currency,
            shipping_method: $order->shipping_method,
            shipping_amount: $order->shipping_amount?->toFloat(),
            notes: $order->notes,
            created_at: $order->created_at,
            // Buyer-facing responses don't need to know which Supplier fulfills their
            // Order; only the owning Supplier and Admins see it (ADR-0001 API Resources).
            supplier_id: Lazy::when(
                fn () => $viewer !== null && ($viewer->isAdmin() || $viewer->id === $order->supplier_id),
                fn () => $order->supplier_id,
            ),
            payment_method: Lazy::whenLoaded('paymentMethod', $order, fn () => $order->paymentMethod->resource_key),
            address: Lazy::whenLoaded('address', $order, fn () => $order->address ? AddressData::fromModel($order->address) : null),
            items: Lazy::whenLoaded('items', $order, fn () => OrderItemData::collect($order->items, DataCollection::class)),
            status_history: Lazy::whenLoaded('statusHistory', $order, fn () => OrderStatusHistoryData::collect($order->statusHistory, DataCollection::class)),
        );
    }
}
