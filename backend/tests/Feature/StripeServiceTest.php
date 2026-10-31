<?php

use App\Models\Order;
use App\Models\User;
use App\Services\StripeService;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\Util\ApiVersion;

/**
 * Captures the request the Stripe SDK would send, instead of calling Stripe.
 * Other tests mock StripeService entirely, so this is the one place that checks
 * the parameters we actually send, which a stripe-php major upgrade can break.
 */
final class RecordingStripeClient implements ClientInterface
{
    /** @var list<array{method: string, url: string, headers: list<string>, params: array}> */
    public array $requests = [];

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $this->requests[] = ['method' => $method, 'url' => $absUrl, 'headers' => $headers, 'params' => $params];

        return [json_encode(['id' => 'cs_test_123', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.test/cs_test_123']), 200, []];
    }
}

beforeEach(function () {
    config(['app.stripe_secret_key' => 'sk_test_fake']);
    $this->stripeClient = new RecordingStripeClient;
    ApiRequestor::setHttpClient($this->stripeClient);
});

afterEach(function () {
    ApiRequestor::setHttpClient(null);
});

it('creates a card-only Checkout Session with the parameters the pinned Stripe API version accepts', function () {
    $buyer = User::factory()->create(['email' => 'buyer@example.com']);
    $this->actingAs($buyer);
    $order = Order::factory()->create(['user_id' => $buyer->id]);
    $lineItems = [['price_data' => ['currency' => 'eur', 'product_data' => ['name' => 'Mug'], 'unit_amount' => 1000], 'quantity' => 2]];

    $session = app(StripeService::class)->createSession($order, $lineItems);

    expect($session->id)->toBe('cs_test_123')
        ->and($this->stripeClient->requests)->toHaveCount(1);

    $request = $this->stripeClient->requests[0];
    expect($request['method'])->toBe('post')
        ->and($request['url'])->toEndWith('/v1/checkout/sessions')
        ->and($request['params'])->toMatchArray([
            // stripe-php 22 removed `payment_method_types` from Checkout Session creation.
            'allowed_payment_method_types' => ['card'],
            'customer_email' => 'buyer@example.com',
            'mode' => 'payment',
            'line_items' => $lineItems,
            'success_url' => config('app.frontend_url').'/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => config('app.frontend_url').'/cancel?order_id='.$order->id,
        ])
        ->and($request['params'])->not->toHaveKey('payment_method_types');
});

it('sends the API version pinned by the installed stripe-php', function () {
    $this->actingAs(User::factory()->create());

    app(StripeService::class)->createSession(Order::factory()->create(), []);

    expect($this->stripeClient->requests[0]['headers'])->toContain('Stripe-Version: '.ApiVersion::CURRENT);
});
