<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * Placeholder detail route so OrderNotification::payload()'s route('my-orders.detail', ...)
 * resolves — the real Buyer order list/detail lands in issue #9 (Buyer's My Orders (list & detail)).
 */
class OrderController extends Controller
{
    public function show(int $order): JsonResponse
    {
        return response()->json(['order_id' => $order]);
    }
}
