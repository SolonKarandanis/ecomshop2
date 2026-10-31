<?php

use App\Models\CartItem;
use App\Models\OrderItem;
use App\ValueObjects\Money;

it('copies the product, quantity, prices and chosen attributes from a cart item', function () {
    $cartItem = new CartItem([
        'product_id' => 7,
        'quantity' => 3,
        'unit_price' => Money::fromCents(1250),
        'total_price' => Money::fromCents(3750),
        'attributes' => ['attribute.color' => 'Blue'],
    ]);

    $orderItem = OrderItem::fromCartItem($cartItem);

    expect($orderItem->product_id)->toBe(7)
        ->and($orderItem->quantity)->toBe(3)
        ->and($orderItem->unit_amount->cents())->toBe(1250)
        ->and($orderItem->total_amount->cents())->toBe(3750)
        ->and($orderItem->getAttribute('attributes'))->toBe(['attribute.color' => 'Blue'])
        ->and($orderItem->exists)->toBeFalse();
});

it('keeps the attributes passed to the normal Eloquent constructor', function () {
    $orderItem = new OrderItem(['product_id' => 7, 'quantity' => 2]);

    expect($orderItem->product_id)->toBe(7)
        ->and($orderItem->quantity)->toBe(2);
});
