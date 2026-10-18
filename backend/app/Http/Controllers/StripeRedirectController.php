<?php

namespace App\Http\Controllers;

use App\Exceptions\OrderException;
use App\Exceptions\PaymentException;
use App\Http\Requests\StripeCancelRequest;
use App\Http\Requests\StripeSuccessRequest;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

/**
 * Called by the Nuxt /success and /cancel pages that Stripe redirects the Buyer to.
 * The webhook is the source of truth for payment status (ADR-0004).
 */
class StripeRedirectController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    /**
     * @throws OrderException
     * @throws PaymentException
     */
    public function success(StripeSuccessRequest $request): JsonResponse
    {
        $order = $this->orderService->confirmStripeRedirect($request->validated('session_id'), $request->user());

        return response()->json($this->orderStatusPayload($order));
    }

    public function cancel(StripeCancelRequest $request): JsonResponse
    {
        $order = $this->orderService->getBuyersOrder((int) $request->validated('order_id'), $request->user());

        return response()->json([
            ...$this->orderStatusPayload($order),
            'retry_url' => $this->orderService->getStripeRetryUrl($order),
        ]);
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
