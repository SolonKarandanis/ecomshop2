<?php

namespace App\Services;

use App\Exceptions\PaymentException;
use App\Models\Order;
use App\Support\FrontendUrl;
use Illuminate\Container\Attributes\Config;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\App;
use Stripe\Checkout\Session;
use Stripe\Event;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\HttpClient\CurlClient;
use Stripe\Stripe;
use Stripe\Webhook;
use UnexpectedValueException;

#[Singleton]
class StripeService
{
    /**
     * stripe-php waits up to 80s for a response (30s to connect) by default.
     * Checkout calls Stripe while it holds a PHP worker and the cart's row lock,
     * so a slow Stripe would tie both up for that long, and a few checkouts
     * would leave Hostinger with no workers for anyone else. Fail fast instead:
     * the Buyer gets a 502 and can try again.
     */
    public const int TIMEOUT_SECONDS = 10;

    public const int CONNECT_TIMEOUT_SECONDS = 3;

    public function __construct(#[Config('app.stripe_secret_key')] ?string $secretKey)
    {
        Stripe::setApiKey($secretKey);
        // The client Stripe uses unless one is injected (tests inject a fake).
        CurlClient::instance()
            ->setTimeout(self::TIMEOUT_SECONDS)
            ->setConnectTimeout(self::CONNECT_TIMEOUT_SECONDS);
    }

    /**
     * Stripe sends the Buyer back to the Nuxt frontend, not the API (ADR-0004),
     * in the language they're checking out in; Stripe's own page uses it too.
     * Stripe fills in {CHECKOUT_SESSION_ID} on success_url only, so cancel_url
     * carries the Order id instead.
     *
     * @throws PaymentException
     */
    public function createSession(Order $order, array $line_items): Session
    {
        $locale = App::getLocale();

        try {
            /** @var array[] $line_items */
            return Session::create([
                // stripe-php 22 removed `payment_method_types` from Checkout Session creation.
                'allowed_payment_method_types' => ['card'],
                'customer_email' => (string) auth()->user()->email,
                'line_items' => $line_items,
                'mode' => 'payment',
                // en, el and tr are all Stripe Checkout languages.
                'locale' => $locale,
                'success_url' => FrontendUrl::to('/success?session_id={CHECKOUT_SESSION_ID}', $locale),
                'cancel_url' => FrontendUrl::to('/cancel?'.http_build_query(['order_id' => $order->id]), $locale),
            ]);
        } catch (ApiErrorException $e) {
            throw PaymentException::createStipeSession($e);
        }
    }

    /**
     * @throws PaymentException
     */
    public function retrieveSession(string $sessionId): Session
    {
        try {
            return Session::retrieve($sessionId);
        } catch (ApiErrorException $e) {
            throw PaymentException::retrieveStipeSession($e);
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
