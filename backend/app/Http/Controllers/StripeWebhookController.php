<?php

namespace App\Http\Controllers;

use App\Exceptions\OrderException;
use App\Services\OrderService;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Stripe\Exception\SignatureVerificationException;
use UnexpectedValueException;

/**
 * Stripe's checkout.session.* events finalize an Order's payment status (ADR-0004).
 * Authenticated by Stripe's signature, not the session, and exempt from CSRF.
 */
class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly StripeService $stripeService,
        private readonly OrderService $orderService,
    ) {}

    /**
     * Acknowledges every verified event, including ignored ones, so Stripe doesn't
     * retry them. A failure while handling one returns 5xx, and Stripe retries it.
     *
     * @throws OrderException
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $event = $this->stripeService->constructWebhookEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
            );
        } catch (SignatureVerificationException|UnexpectedValueException) {
            return response()->apiError(null, 'Invalid Stripe webhook signature or payload', 400, 'InvalidStripeWebhook');
        }

        // Stripe retries a failed event with the same ID, so this ties each
        // attempt's log lines and notifications back to the event.
        Context::add([
            'stripe_event_id' => $event->id,
            'stripe_event_type' => $event->type,
        ]);

        $this->orderService->handleStripeEvent($event);

        return response()->json(['received' => true]);
    }
}
