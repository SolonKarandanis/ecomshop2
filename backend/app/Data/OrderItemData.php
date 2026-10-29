<?php

namespace App\Data;

use App\Models\OrderItem;
use Spatie\LaravelData\Lazy;

class OrderItemData extends BaseData
{
    public function __construct(
        public int $id,
        public int $product_id,
        public Lazy|ProductData|null $product,
        public int $quantity,
        public ?float $unit_amount,
        public ?float $total_amount,
        public ?array $attributes,
        // attribute name → chosen option name, for display (e.g. ["Size" => "M"]).
        public array $attribute_labels,
    ) {}

    public static function fromModel(OrderItem $orderItem): self
    {
        return new self(
            id: $orderItem->id,
            product_id: $orderItem->product_id,
            product: Lazy::whenLoaded('product', $orderItem, fn () => ProductData::fromModel($orderItem->product)),
            quantity: $orderItem->quantity,
            unit_amount: $orderItem->unit_amount?->toFloat(),
            total_amount: $orderItem->total_amount?->toFloat(),
            attributes: $orderItem->attributes,
            attribute_labels: $orderItem->getAttributeNamesAndOptions(),
        );
    }
}
