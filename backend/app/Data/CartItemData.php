<?php

namespace App\Data;

use App\Models\CartItem;

class CartItemData extends BaseData
{
    public function __construct(
        public string $id,
        public int $product_id,
        public ?string $product_name,
        public ?string $product_slug,
        public int $quantity,
        public float $unit_price,
        public float $total_price,
        public array $attributes,
    ) {}

    public static function fromModel(CartItem $cartItem): self
    {
        return new self(
            id: (string) ($cartItem->id ?? $cartItem->id_from_cookie),
            product_id: $cartItem->product_id,
            product_name: $cartItem->product?->name,
            product_slug: $cartItem->product?->slug,
            quantity: $cartItem->quantity,
            unit_price: (float) $cartItem->unit_price,
            total_price: (float) $cartItem->total_price,
            attributes: $cartItem->attributes ?? [],
        );
    }
}
