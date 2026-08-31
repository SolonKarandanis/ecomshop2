<?php

namespace App\Payments;

use App\Exceptions\PaymentException;
use App\Models\Order;
use App\Repositories\StripeOrderDetailRepository;
use App\Services\StripeService;
use Illuminate\Support\Facades\Log;

readonly class StripePaymentHandler implements PaymentHandlerInterface
{
    public function __construct(
        private StripeService               $stripeService,
        private StripeOrderDetailRepository $stripeOrderDetailRepository,
    ) {}

    /**
     * @throws PaymentException
     */
    public function process(Order $order, array $lineItems): string {
        Log::debug('OrderService paymentMethod: Stripe');
        $session = $this->stripeService->createSession($lineItems);
        Log::debug('OrderService $sessionCheckout:',[$session->id]);
        $this->stripeOrderDetailRepository->createStripeOrderDetail($order->id, $session->id);
        return $session->url;
    }

}
