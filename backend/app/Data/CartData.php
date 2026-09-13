<?php

namespace App\Data;

use App\Models\Cart;
use Spatie\LaravelData\DataCollection;

class CartData extends BaseData
{
    public function __construct(
        public float $total_price,
        /** @var DataCollection<int, CartItemData> */
        public DataCollection $items,
    ) {}

    public static function fromModel(Cart $cart): self
    {
        return new self(
            total_price: (float) $cart->total_price,
            items: CartItemData::collect($cart->cartItems, DataCollection::class),
        );
    }
}
