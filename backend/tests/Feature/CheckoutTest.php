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
use App\Models\SavedAddress;
use App\Models\StripeOrderDetail;
use App\Models\User;
use App\Notifications\OrderNotification;
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

    forgetRequestState();
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

function checkoutWithCartItem(string $paymentMethod, array $payloadOverrides = []): array
{
    PaymentMethod::firstOrCreate(['resource_key' => $paymentMethod]);
    [$buyer, $login] = loggedInBuyer();
    $product = Product::factory()->create(['price' => 25]);

    $add = simulateNextCheckoutRequest($login)->postJson('/api/cart/items', [
        'product_id' => $product->id,
        'quantity' => 1,
    ])->assertOk();

    $checkout = simulateNextCheckoutRequest($add)
        ->postJson('/api/checkout', [...validCheckoutPayload($paymentMethod), ...$payloadOverrides])
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

it('charges the exact price, cents included, on the Order', function () {
    Notification::fake();
    Mail::fake();
    PaymentMethod::firstOrCreate(['resource_key' => PaymentMethodEnum::CASH_ON_DELIVERY->value]);
    [$buyer, $login] = loggedInBuyer();
    $product = Product::factory()->create(['price' => 19.99]);

    $add = simulateNextCheckoutRequest($login)->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2])->assertOk();
    simulateNextCheckoutRequest($add)->postJson('/api/checkout', validCheckoutPayload())->assertOk();

    $order = Order::where('user_id', $buyer->id)->sole();
    expect((float) $order->grand_total)->toBe(39.98);
    expect($order->items()->sole())
        ->unit_amount->toEqual('19.99')
        ->total_amount->toEqual('39.98');
});

it('totals the Order from the cart after the Buyer adds more of an item', function () {
    Notification::fake();
    Mail::fake();
    PaymentMethod::firstOrCreate(['resource_key' => PaymentMethodEnum::CASH_ON_DELIVERY->value]);
    [$buyer, $login] = loggedInBuyer();
    $product = Product::factory()->create(['price' => 10]);

    $first = simulateNextCheckoutRequest($login)->postJson('/api/cart/items', ['product_id' => $product->id])->assertOk();
    $again = simulateNextCheckoutRequest($first)->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2])->assertOk();
    simulateNextCheckoutRequest($again)->postJson('/api/checkout', validCheckoutPayload())->assertOk();

    $order = Order::where('user_id', $buyer->id)->sole();
    expect((float) $order->grand_total)->toBe(30.0);
    expect((float) $order->items()->sum('total_amount'))->toBe(30.0);
});

it('sends Stripe each unit price as a whole number of cents', function (float $price, int $cents) {
    Notification::fake();
    Mail::fake();
    config(['app.stripe_secret_key' => 'sk_test_fake']);
    PaymentMethod::firstOrCreate(['resource_key' => PaymentMethodEnum::STRIPE->value]);
    [, $login] = loggedInBuyer();
    $product = Product::factory()->create(['price' => $price]);
    $stripe = new FakeStripeHttpClient;
    ApiRequestor::setHttpClient($stripe);

    try {
        $add = simulateNextCheckoutRequest($login)->postJson('/api/cart/items', ['product_id' => $product->id])->assertOk();
        simulateNextCheckoutRequest($add)
            ->postJson('/api/checkout', validCheckoutPayload(PaymentMethodEnum::STRIPE->value))
            ->assertOk();
    } finally {
        ApiRequestor::setHttpClient(null);
    }

    expect($stripe->requests[0]['params']['line_items'][0]['price_data']['unit_amount'])->toBe($cents);
})->with([
    // price * 100 in floating point isn't exact: 19.99 * 100 is 1998.9999999999998.
    '19.99' => [19.99, 1999],
    '4.35' => [4.35, 435],
    '1.15' => [1.15, 115],
    '8.20' => [8.20, 820],
    '25.00' => [25.00, 2500],
]);

it("saves the shipping address as the Buyer's default when saveAddress is set and the address book is empty", function () {
    Notification::fake();
    Mail::fake();

    [$buyer] = checkoutWithCartItem(PaymentMethodEnum::CASH_ON_DELIVERY->value, ['saveAddress' => true]);

    expect(SavedAddress::where('user_id', $buyer->id)->sole()->only([
        'first_name', 'last_name', 'phone', 'street_address', 'city', 'country', 'postal_code', 'is_default',
    ]))->toBe([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'phone' => '555-1234',
        'street_address' => '1 Main St',
        'city' => 'Athens',
        'country' => 'Greece',
        'postal_code' => '12345',
        'is_default' => true,
    ]);
});

it("doesn't save a duplicate when the Buyer already has an identical address", function () {
    Notification::fake();
    Mail::fake();
    PaymentMethod::firstOrCreate(['resource_key' => PaymentMethodEnum::CASH_ON_DELIVERY->value]);
    [$buyer, $login] = loggedInBuyer();
    SavedAddress::factory()->for($buyer)->create([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'phone' => '555-1234',
        'street_address' => '1 Main St',
        'city' => 'Athens',
        'country' => 'Greece',
        'postal_code' => '12345',
    ]);
    $other = SavedAddress::factory()->for($buyer)->default()->create(['city' => 'Sparta']);
    $product = Product::factory()->create();

    $add = simulateNextCheckoutRequest($login)->postJson('/api/cart/items', ['product_id' => $product->id])->assertOk();
    simulateNextCheckoutRequest($add)
        ->postJson('/api/checkout', [...validCheckoutPayload(), 'saveAddress' => true])
        ->assertOk();

    expect(SavedAddress::where('user_id', $buyer->id)->count())->toBe(2);
    expect($other->fresh()->is_default)->toBeTrue();
});

it("adds a new address as non-default when the Buyer's address book already has one", function () {
    Notification::fake();
    Mail::fake();
    PaymentMethod::firstOrCreate(['resource_key' => PaymentMethodEnum::CASH_ON_DELIVERY->value]);
    [$buyer, $login] = loggedInBuyer();
    $existing = SavedAddress::factory()->for($buyer)->default()->create(['city' => 'Sparta']);
    $product = Product::factory()->create();

    $add = simulateNextCheckoutRequest($login)->postJson('/api/cart/items', ['product_id' => $product->id])->assertOk();
    simulateNextCheckoutRequest($add)
        ->postJson('/api/checkout', [...validCheckoutPayload(), 'saveAddress' => true])
        ->assertOk();

    expect(SavedAddress::where('user_id', $buyer->id)->where('city', 'Athens')->sole()->is_default)->toBeFalse();
    expect($existing->fresh()->is_default)->toBeTrue();
});

it("doesn't touch the address book when saveAddress isn't set", function () {
    Notification::fake();
    Mail::fake();

    checkoutWithCartItem(PaymentMethodEnum::CASH_ON_DELIVERY->value);

    expect(SavedAddress::count())->toBe(0);
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

it('rejects checkout with a payment method that does not exist or is switched off', function (string $paymentMethod, bool $exists) {
    if ($exists) {
        PaymentMethod::firstOrCreate(['resource_key' => $paymentMethod], ['is_active' => false]);
    }
    [$buyer, $login] = loggedInBuyer();
    $product = Product::factory()->create();

    $add = simulateNextCheckoutRequest($login)->postJson('/api/cart/items', ['product_id' => $product->id])->assertOk();

    simulateNextCheckoutRequest($add)
        ->postJson('/api/checkout', validCheckoutPayload($paymentMethod))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('paymentMethod');

    expect(Order::count())->toBe(0);
})->with([
    'unknown' => ['payment.method.bitcoin', false],
    'switched off' => [PaymentMethodEnum::PAYPAL->value, true],
]);

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
    // A valid payload, so validation passes and the request reaches the buyer-action gate.
    PaymentMethod::firstOrCreate(['resource_key' => PaymentMethodEnum::CASH_ON_DELIVERY->value]);
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
