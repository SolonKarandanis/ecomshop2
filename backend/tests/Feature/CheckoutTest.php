<?php

use App\Enums\PaymentMethodEnum;
use App\Enums\RolesEnum;
use App\Exceptions\EmptyCartException;
use App\Mail\OrderPlaced;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\User;
use App\Notifications\OrderNotification;
use App\Services\CartService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
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
        ->postJson('/login', ['email' => $buyer->email, 'password' => 'password'])
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

    $add = simulateNextCheckoutRequest($login)->postJson('/cart/items', [
        'product_id' => $product->id,
        'quantity' => 2,
    ])->assertOk();

    $checkout = simulateNextCheckoutRequest($add)
        ->postJson('/checkout', validCheckoutPayload())
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

it('rejects checkout with an empty cart', function () {
    PaymentMethod::firstOrCreate(['resource_key' => PaymentMethodEnum::CASH_ON_DELIVERY->value]);
    [$buyer, $login] = loggedInBuyer();

    $checkout = simulateNextCheckoutRequest($login)
        ->postJson('/checkout', validCheckoutPayload())
        ->assertBadRequest();

    expect($checkout->json('message'))->toBe(EmptyCartException::emptyCart()->getMessage());
    expect(Order::count())->toBe(0);
});

it('rejects checkout for a guest', function () {
    $product = Product::factory()->create();

    $add = simulateNextCheckoutRequest()->postJson('/cart/items', ['product_id' => $product->id])->assertOk();

    simulateNextCheckoutRequest($add)
        ->postJson('/checkout', validCheckoutPayload())
        ->assertUnauthorized();
});

it('rejects checkout for an authenticated Supplier', function () {
    Role::firstOrCreate(['name' => RolesEnum::ROLE_SUPPLIER->value]);
    $supplier = User::factory()->create(['password' => Hash::make('password')]);
    $supplier->assignRole(RolesEnum::ROLE_SUPPLIER->value);

    $login = simulateNextCheckoutRequest()
        ->postJson('/login', ['email' => $supplier->email, 'password' => 'password'])
        ->assertOk();

    simulateNextCheckoutRequest($login)
        ->postJson('/checkout', validCheckoutPayload())
        ->assertForbidden();
});

it('rejects checkout with a missing required field', function () {
    PaymentMethod::firstOrCreate(['resource_key' => PaymentMethodEnum::CASH_ON_DELIVERY->value]);
    [, $login] = loggedInBuyer();
    $product = Product::factory()->create();

    $add = simulateNextCheckoutRequest($login)->postJson('/cart/items', ['product_id' => $product->id])->assertOk();

    $payload = validCheckoutPayload();
    unset($payload['address']);

    simulateNextCheckoutRequest($add)
        ->postJson('/checkout', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('address');
});
