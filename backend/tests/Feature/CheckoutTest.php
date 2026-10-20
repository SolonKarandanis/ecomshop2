<?php

use App\Enums\PaymentMethodEnum;
use App\Enums\RolesEnum;
use App\Exceptions\CartException;
use App\Exceptions\EmptyCartException;
use App\Mail\OrderPlaced;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\StripeOrderDetail;
use App\Models\User;
use App\Notifications\OrderNotification;
use App\Services\CartService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Tests\TestCase;

function simulateNextCheckoutRequest(?TestResponse $previous = null): TestCase
{
    $client = test()->withCredentials()->withHeader('Origin', 'http://localhost:3000');

    if ($previous !== null) {
        foreach ($previous->headers->getCookies() as $cookie) {
            $client->withUnencryptedCookie($cookie->getName(), $cookie->getValue());
        }
    }

    app()->forgetInstance(CartService::class);
    app('auth')->forgetGuards();

    return $client;
}

function loggedInBuyer(): array
{
    Role::firstOrCreate(['name' => RolesEnum::ROLE_BUYER->value]);
    $buyer = User::factory()->create(['password' => Hash::make('password')]);
    $buyer->assignRole(RolesEnum::ROLE_BUYER->value);

    $login = simulateNextCheckoutRequest()
        ->postJson('/api/login', ['email' => $buyer->email, 'password' => 'password'])
        ->assertOk();

    return [$buyer, $login];
}

function validCheckoutPayload(string $paymentMethod = PaymentMethodEnum::CASH_ON_DELIVERY->value): array
{
    return [
        'firstName' => 'Jane',
        'lastName' => 'Doe',
        'phone' => '555-1234',
        'address' => '1 Main St',
        'city' => 'Athens',
        'country' => 'Greece',
        'zipCode' => '12345',
        'paymentMethod' => $paymentMethod,
    ];
}

it('checks out a non-empty cart, creates the Order, clears the cart and notifies the Buyer', function () {
    Notification::fake();
    Mail::fake();
    PaymentMethod::firstOrCreate(['resource_key' => PaymentMethodEnum::CASH_ON_DELIVERY->value]);

    [$buyer, $login] = loggedInBuyer();
    $product = Product::factory()->create(['price' => 25]);

    $add = simulateNextCheckoutRequest($login)->postJson('/api/cart/items', [
        'product_id' => $product->id,
        'quantity' => 2,
    ])->assertOk();

    $checkout = simulateNextCheckoutRequest($add)
        ->postJson('/api/checkout', validCheckoutPayload())
        ->assertOk();

    expect($checkout->json('redirect_url'))->not->toBeEmpty();

    $this->assertDatabaseHas('orders', ['user_id' => $buyer->id]);
    $this->assertDatabaseHas('order_items', ['product_id' => $product->id, 'quantity' => 2]);
    $this->assertDatabaseHas('addresses', [
        'user_id' => $buyer->id,
        'street_address' => '1 Main St',
        'city' => 'Athens',
    ]);
    $this->assertDatabaseCount('cart_items', 0);

    Notification::assertSentTo($buyer->fresh(), OrderNotification::class);
    Mail::assertQueued(OrderPlaced::class);
});

/**
 * Stands in for Stripe's HTTP client: records each request and answers with a
 * Checkout Session, so checkout can be tested without network calls.
 */
final class FakeStripeHttpClient implements ClientInterface
{
    /** @var array<int, array{method: string, url: string, params: array}> */
    public array $requests = [];

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $this->requests[] = ['method' => $method, 'url' => $absUrl, 'params' => $params];

        return [json_encode([
            'id' => 'cs_test_checkout',
            'object' => 'checkout.session',
            'url' => 'https://checkout.stripe.com/c/pay/cs_test_checkout',
        ]), 200, []];
    }
}

function checkoutWithCartItem(string $paymentMethod): array
{
    PaymentMethod::firstOrCreate(['resource_key' => $paymentMethod]);
    [$buyer, $login] = loggedInBuyer();
    $product = Product::factory()->create(['price' => 25]);

    $add = simulateNextCheckoutRequest($login)->postJson('/api/cart/items', [
        'product_id' => $product->id,
        'quantity' => 1,
    ])->assertOk();

    $checkout = simulateNextCheckoutRequest($add)
        ->postJson('/api/checkout', validCheckoutPayload($paymentMethod))
        ->assertOk();

    return [$buyer, $checkout];
}

it('sends a Cash on Delivery Buyer to the frontend success page for the new Order', function () {
    Notification::fake();
    Mail::fake();
    config(['app.frontend_url' => 'https://shop.test']);

    [$buyer, $checkout] = checkoutWithCartItem(PaymentMethodEnum::CASH_ON_DELIVERY->value);

    $order = Order::where('user_id', $buyer->id)->sole();
    expect($checkout->json('redirect_url'))->toBe("https://shop.test/success?order_id={$order->id}");
});

it('creates the Stripe session with success and cancel URLs on the frontend, and stores its session id', function () {
    Notification::fake();
    Mail::fake();
    config(['app.frontend_url' => 'https://shop.test', 'app.stripe_secret_key' => 'sk_test_fake']);
    $stripe = new FakeStripeHttpClient;
    ApiRequestor::setHttpClient($stripe);

    try {
        [$buyer, $checkout] = checkoutWithCartItem(PaymentMethodEnum::STRIPE->value);
    } finally {
        ApiRequestor::setHttpClient(null);
    }

    $order = Order::where('user_id', $buyer->id)->sole();
    expect($checkout->json('redirect_url'))->toBe('https://checkout.stripe.com/c/pay/cs_test_checkout');
    expect($stripe->requests)->toHaveCount(1);
    expect($stripe->requests[0]['params'])
        ->success_url->toBe('https://shop.test/success?session_id={CHECKOUT_SESSION_ID}')
        ->cancel_url->toBe("https://shop.test/cancel?order_id={$order->id}");
    expect(StripeOrderDetail::where('order_id', $order->id)->value('session_id'))->toBe('cs_test_checkout');
});

it('rejects checkout with an empty cart', function () {
    PaymentMethod::firstOrCreate(['resource_key' => PaymentMethodEnum::CASH_ON_DELIVERY->value]);
    [$buyer, $login] = loggedInBuyer();

    $checkout = simulateNextCheckoutRequest($login)
        ->postJson('/api/checkout', validCheckoutPayload())
        ->assertBadRequest();

    expect($checkout->json('message'))->toBe(EmptyCartException::emptyCart()->getMessage());
    expect(Order::count())->toBe(0);
});

it('rejects checkout of a cart holding more than one Supplier', function () {
    PaymentMethod::firstOrCreate(['resource_key' => PaymentMethodEnum::CASH_ON_DELIVERY->value]);
    [$buyer, $login] = loggedInBuyer();

    // Adding to the cart can't produce this; write it directly to hit the safety net.
    $cart = Cart::create(['user_id' => $buyer->id, 'total_price' => 0]);
    foreach ([User::factory()->create(), User::factory()->create()] as $supplier) {
        $product = Product::factory()->create(['supplier_id' => $supplier->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => $product->price,
            'total_price' => $product->price,
        ]);
    }

    $checkout = simulateNextCheckoutRequest($login)
        ->postJson('/api/checkout', validCheckoutPayload())
        ->assertBadRequest();

    expect($checkout->json('message'))->toBe(CartException::supplierMismatch()->getMessage());
    expect(Order::count())->toBe(0);
    expect(CartItem::where('cart_id', $cart->id)->count())->toBe(2);
});

it('rejects checkout for a guest', function () {
    $product = Product::factory()->create();

    $add = simulateNextCheckoutRequest()->postJson('/api/cart/items', ['product_id' => $product->id])->assertOk();

    simulateNextCheckoutRequest($add)
        ->postJson('/api/checkout', validCheckoutPayload())
        ->assertUnauthorized();
});

it('rejects checkout for an authenticated Supplier', function () {
    Role::firstOrCreate(['name' => RolesEnum::ROLE_SUPPLIER->value]);
    $supplier = User::factory()->create(['password' => Hash::make('password')]);
    $supplier->assignRole(RolesEnum::ROLE_SUPPLIER->value);

    $login = simulateNextCheckoutRequest()
        ->postJson('/api/login', ['email' => $supplier->email, 'password' => 'password'])
        ->assertOk();

    simulateNextCheckoutRequest($login)
        ->postJson('/api/checkout', validCheckoutPayload())
        ->assertForbidden();
});

it('rejects checkout with a missing required field', function () {
    PaymentMethod::firstOrCreate(['resource_key' => PaymentMethodEnum::CASH_ON_DELIVERY->value]);
    [, $login] = loggedInBuyer();
    $product = Product::factory()->create();

    $add = simulateNextCheckoutRequest($login)->postJson('/api/cart/items', ['product_id' => $product->id])->assertOk();

    $payload = validCheckoutPayload();
    unset($payload['address']);

    simulateNextCheckoutRequest($add)
        ->postJson('/api/checkout', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('address');
});
