<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StripeRedirectController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function success(Request $request): JsonResponse
    {
        $order = $this->orderService->getUsersLatestOrder($request->user()->id);

        $sessionId = $request->query('session_id');
        if (! empty($sessionId)) {
            $order = $this->orderService->successOrFailStripeOrder($sessionId, $order);
        }

        return response()->json($this->orderStatusPayload($order));
    }

    public function cancel(Request $request): JsonResponse
    {
        $order = $this->orderService->getUsersLatestOrder($request->user()->id);

        return response()->json($this->orderStatusPayload($order));
    }

    /**
     * @return array{order_id: int, order_status: string, payment_status: string|null}
     */
    private function orderStatusPayload(Order $order): array
    {
        return [
            'order_id' => $order->id,
            'order_status' => $order->order_status,
            'payment_status' => $order->payment_status,
        ];
    }
}
