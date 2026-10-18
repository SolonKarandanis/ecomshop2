<?php

namespace App\Services;

use App\Exceptions\PaymentException;
use App\Models\Order;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Event;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Stripe;
use Stripe\Webhook;
use UnexpectedValueException;

#[Singleton]
class StripeService
{

    public function __construct(){
        Stripe::setApiKey(config('app.stripe_secret_key'));
    }

    /**
     * Stripe sends the Buyer back to the Nuxt frontend, not the API (ADR-0004).
     * Stripe fills in {CHECKOUT_SESSION_ID} on success_url only, so cancel_url
     * carries the Order id instead.
     *
     * @throws PaymentException
     */
    public function createSession(Order $order, array $line_items):Session{
        $frontendUrl = config('app.frontend_url');

        try {
            /** @var array[] $line_items */
            return Session::create([
                'payment_method_types' => ['card'],
                'customer_email' => (string) auth()->user()->email,
                'line_items'=>$line_items,
                'mode'=>'payment',
                'success_url'=>$frontendUrl.'/success?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url'=>$frontendUrl.'/cancel?'.http_build_query(['order_id' => $order->id]),
            ]);
        } catch (ApiErrorException $e) {
            Log::error($e);
            throw PaymentException::createStipeSession();
        }
    }


    /**
     * @throws PaymentException
     */
    public function retrieveSession(string $sessionId):Session{
        try {
            return Session::retrieve($sessionId);
        } catch (ApiErrorException $e) {
            Log::error($e);
            throw PaymentException::retrieveStipeSession();
        }
    }

    /**
     * Verifies the Stripe-Signature header against the webhook secret and builds the Event.
     *
     * @throws SignatureVerificationException when the signature is missing or wrong
     * @throws UnexpectedValueException when the payload isn't a valid event
     */
    public function constructWebhookEvent(string $payload, string $signature): Event
    {
        return Webhook::constructEvent($payload, $signature, (string) config('app.stripe_webhook_secret'));
    }
}
