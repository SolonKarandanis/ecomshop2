<?php

use App\Mail\OrderPlaced;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;

it('renders the Order placed email with the item prices and totals', function () {
    $product = Product::factory()->create(['name' => 'Mug']);
    $order = Order::factory()->create(['grand_total' => '44.98', 'shipping_amount' => '5.00']);
    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_amount' => '19.99',
        'total_amount' => '39.98',
    ]);

    $html = (new OrderPlaced($order->fresh()))->render();

    expect($html)
        ->toContain('Mug')
        ->toContain('$19.99')
        ->toContain('$39.98')
        ->toContain('$5.00')
        ->toContain('$44.98');
});
