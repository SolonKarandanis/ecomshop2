<?php

use App\Enums\RolesEnum;
use App\Exceptions\CartException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Each Pest test reuses one booted app/container across every HTTP call it
 * makes, unlike separate real requests. That leaves cookies unsent by
 * default (postJson/getJson send none unless told to) and lets
 * request-scoped singletons (the auth guard, CartService's cachedCart)
 * leak stale state between calls. simulateNextRequest() re-attaches the
 * previous response's cookies and drops those singletons, so each call
 * behaves like an independent request the way it would in production.
 */
function simulateNextRequest(?TestResponse $previous = null): TestCase
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

function seedBuyerRole(): void
{
    Role::firstOrCreate(['name' => RolesEnum::ROLE_BUYER->value]);
}

it('lets a guest add a product to the cart and persists it across requests via cookies', function () {
    $product = Product::factory()->create();

    $add = simulateNextRequest()->postJson('/api/cart/items', [
        'product_id' => $product->id,
        'quantity' => 2,
    ])->assertOk();

    expect($add->json('data.items'))->toHaveCount(1);
    $add->assertCookie('cart')->assertCookie('cartItems');

    $show = simulateNextRequest($add)->getJson('/api/cart')->assertOk();
    expect($show->json('data.items.0.product_id'))->toBe($product->id);
    expect($show->json('data.items.0.quantity'))->toBe(2);

    expect(Cart::count())->toBe(0);
});

it("persists an authenticated Buyer's cart via the database", function () {
    seedBuyerRole();
    $buyer = User::factory()->create(['password' => Hash::make('password')]);
    $buyer->assignRole(RolesEnum::ROLE_BUYER->value);
    $product = Product::factory()->create();

    $login = simulateNextRequest()
        ->postJson('/api/login', ['email' => $buyer->email, 'password' => 'password'])
        ->assertOk();

    $add = simulateNextRequest($login)->postJson('/api/cart/items', [
        'product_id' => $product->id,
        'quantity' => 3,
    ])->assertOk();

    $this->assertDatabaseHas('cart_items', [
        'product_id' => $product->id,
        'quantity' => 3,
    ]);

    $show = simulateNextRequest($add)->getJson('/api/cart')->assertOk();
    expect($show->json('data.items.0.product_id'))->toBe($product->id);
});

it('rejects adding a product from a second Supplier to a non-empty guest cart', function () {
    $supplierA = User::factory()->create();
    $supplierB = User::factory()->create();
    $productA = Product::factory()->create(['supplier_id' => $supplierA->id]);
    $productB = Product::factory()->create(['supplier_id' => $supplierB->id]);

    $add = simulateNextRequest()->postJson('/api/cart/items', ['product_id' => $productA->id])->assertOk();

    $reject = simulateNextRequest($add)->postJson('/api/cart/items', ['product_id' => $productB->id])
        ->assertBadRequest();
    expect($reject->json('message'))->toBe(CartException::supplierMismatch()->getMessage());

    $show = simulateNextRequest($reject)->getJson('/api/cart')->assertOk();
    expect($show->json('data.items'))->toHaveCount(1);
});

it('rejects adding a product from a second Supplier to a non-empty authenticated Buyer cart', function () {
    seedBuyerRole();
    $buyer = User::factory()->create(['password' => Hash::make('password')]);
    $buyer->assignRole(RolesEnum::ROLE_BUYER->value);

    $supplierA = User::factory()->create();
    $supplierB = User::factory()->create();
    $productA = Product::factory()->create(['supplier_id' => $supplierA->id]);
    $productB = Product::factory()->create(['supplier_id' => $supplierB->id]);

    $login = simulateNextRequest()
        ->postJson('/api/login', ['email' => $buyer->email, 'password' => 'password'])
        ->assertOk();

    $add = simulateNextRequest($login)->postJson('/api/cart/items', ['product_id' => $productA->id])->assertOk();

    simulateNextRequest($add)->postJson('/api/cart/items', ['product_id' => $productB->id])
        ->assertBadRequest();

    $this->assertDatabaseMissing('cart_items', ['product_id' => $productB->id]);
});

it('transfers the guest cart to the database on login (TransferGuestCartToUser)', function () {
    seedBuyerRole();
    $buyer = User::factory()->create(['password' => Hash::make('password')]);
    $buyer->assignRole(RolesEnum::ROLE_BUYER->value);
    $product = Product::factory()->create();

    $add = simulateNextRequest()->postJson('/api/cart/items', [
        'product_id' => $product->id,
        'quantity' => 4,
    ])->assertOk();

    $login = simulateNextRequest($add)
        ->postJson('/api/login', ['email' => $buyer->email, 'password' => 'password'])
        ->assertOk();

    $this->assertDatabaseHas('cart_items', [
        'product_id' => $product->id,
        'quantity' => 4,
    ]);

    $show = simulateNextRequest($login)->getJson('/api/cart')->assertOk();
    expect($show->json('data.items'))->toHaveCount(1);
    expect($show->json('data.items.0.quantity'))->toBe(4);
});

it('updates a guest cart item quantity', function () {
    $product = Product::factory()->create();

    $add = simulateNextRequest()->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertOk();
    $itemId = $add->json('data.items.0.id');

    $update = simulateNextRequest($add)->patchJson("/api/cart/items/{$itemId}", ['quantity' => 5])->assertOk();

    expect($update->json('data.items.0.quantity'))->toBe(5);
});

it('updates an authenticated Buyer cart item quantity', function () {
    seedBuyerRole();
    $buyer = User::factory()->create(['password' => Hash::make('password')]);
    $buyer->assignRole(RolesEnum::ROLE_BUYER->value);
    $product = Product::factory()->create();

    $login = simulateNextRequest()
        ->postJson('/api/login', ['email' => $buyer->email, 'password' => 'password'])
        ->assertOk();

    $add = simulateNextRequest($login)->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertOk();
    $itemId = $add->json('data.items.0.id');

    simulateNextRequest($add)->patchJson("/api/cart/items/{$itemId}", ['quantity' => 7])->assertOk();

    $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 7]);
});

it('removes a guest cart item', function () {
    $product = Product::factory()->create();

    $add = simulateNextRequest()->postJson('/api/cart/items', ['product_id' => $product->id])->assertOk();
    $itemId = $add->json('data.items.0.id');

    $remove = simulateNextRequest($add)->deleteJson("/api/cart/items/{$itemId}")->assertOk();

    expect($remove->json('data.items'))->toHaveCount(0);
});

it('removes an authenticated Buyer cart item', function () {
    seedBuyerRole();
    $buyer = User::factory()->create(['password' => Hash::make('password')]);
    $buyer->assignRole(RolesEnum::ROLE_BUYER->value);
    $product = Product::factory()->create();

    $login = simulateNextRequest()
        ->postJson('/api/login', ['email' => $buyer->email, 'password' => 'password'])
        ->assertOk();

    $add = simulateNextRequest($login)->postJson('/api/cart/items', ['product_id' => $product->id])->assertOk();
    $itemId = $add->json('data.items.0.id');

    simulateNextRequest($add)->deleteJson("/api/cart/items/{$itemId}")->assertOk();

    $this->assertDatabaseMissing('cart_items', ['product_id' => $product->id]);
});

it('rejects adding to cart for an authenticated Supplier', function () {
    Role::firstOrCreate(['name' => RolesEnum::ROLE_SUPPLIER->value]);
    $supplier = User::factory()->create(['password' => Hash::make('password')]);
    $supplier->assignRole(RolesEnum::ROLE_SUPPLIER->value);
    $product = Product::factory()->create();

    $login = simulateNextRequest()
        ->postJson('/api/login', ['email' => $supplier->email, 'password' => 'password'])
        ->assertOk();

    simulateNextRequest($login)->postJson('/api/cart/items', ['product_id' => $product->id])->assertForbidden();
});

it('returns an empty cart for a fresh guest', function () {
    simulateNextRequest()
        ->getJson('/api/cart')
        ->assertOk()
        ->assertJsonPath('data.items', []);
});

it('does not leave a stale cached empty cart after transferring an empty guest cart on login (regression)', function () {
    seedBuyerRole();
    $buyer = User::factory()->create();
    $buyer->assignRole(RolesEnum::ROLE_BUYER->value);
    $product = Product::factory()->create();

    $cart = Cart::create(['user_id' => $buyer->id, 'total_price' => 0]);
    CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => $product->price,
        'total_price' => $product->price->multiply(2),
    ]);

    $this->actingAs($buyer);

    /** @var CartService $cartService */
    $cartService = app(CartService::class);

    // No guest cookies on this request, so the transfer sees an empty cookie
    // cart and takes the early-return path — the one that used to leave a
    // stale empty cart cached on the singleton for the rest of the request.
    $cartService->moveCartItemsToDatabase();

    expect($cartService->getCart()->cartItems)->toHaveCount(1);
});

/**
 * Logs in a Buyer who already has a saved cart holding $savedProduct, after
 * building a guest cart holding $guestProduct. Returns the login response.
 */
function loginWithGuestAndSavedCarts(User $buyer, Product $savedProduct, Product $guestProduct): TestResponse
{
    $cart = Cart::create(['user_id' => $buyer->id, 'total_price' => 0]);
    CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => $savedProduct->id,
        'quantity' => 1,
        'unit_price' => $savedProduct->price,
        'total_price' => $savedProduct->price,
    ]);

    $add = simulateNextRequest()->postJson('/api/cart/items', ['product_id' => $guestProduct->id])->assertOk();

    return simulateNextRequest($add)
        ->postJson('/api/login', ['email' => $buyer->email, 'password' => 'password'])
        ->assertOk();
}

it('merges the guest cart into the saved cart at login when both are from the same Supplier', function () {
    seedBuyerRole();
    $buyer = User::factory()->create(['password' => Hash::make('password')]);
    $buyer->assignRole(RolesEnum::ROLE_BUYER->value);
    $supplier = User::factory()->create();
    $savedProduct = Product::factory()->create(['supplier_id' => $supplier->id]);
    $guestProduct = Product::factory()->create(['supplier_id' => $supplier->id]);

    $login = loginWithGuestAndSavedCarts($buyer, $savedProduct, $guestProduct);

    $show = simulateNextRequest($login)->getJson('/api/cart')->assertOk();
    expect(collect($show->json('data.items'))->pluck('product_id')->sort()->values()->all())
        ->toBe(collect([$savedProduct->id, $guestProduct->id])->sort()->values()->all());
    expect($show->json('data.notice'))->toBeNull();
});

it('replaces the saved cart with the guest cart at login when they are from different Suppliers, with a one-time notice', function () {
    seedBuyerRole();
    $buyer = User::factory()->create(['password' => Hash::make('password')]);
    $buyer->assignRole(RolesEnum::ROLE_BUYER->value);
    $savedProduct = Product::factory()->create(['supplier_id' => User::factory()->create()->id]);
    $guestProduct = Product::factory()->create(['supplier_id' => User::factory()->create()->id]);

    $login = loginWithGuestAndSavedCarts($buyer, $savedProduct, $guestProduct);

    $this->assertDatabaseMissing('cart_items', ['product_id' => $savedProduct->id]);

    $show = simulateNextRequest($login)->getJson('/api/cart')->assertOk();
    expect($show->json('data.items'))->toHaveCount(1);
    expect($show->json('data.items.0.product_id'))->toBe($guestProduct->id);
    expect($show->json('data.notice'))->toBe(__('messages.cart_notices.saved_cart_replaced'));

    $again = simulateNextRequest($show)->getJson('/api/cart')->assertOk();
    expect($again->json('data.notice'))->toBeNull();
});

it('keeps the saved cart at login when the guest cart is empty', function () {
    seedBuyerRole();
    $buyer = User::factory()->create(['password' => Hash::make('password')]);
    $buyer->assignRole(RolesEnum::ROLE_BUYER->value);
    $product = Product::factory()->create();
    $cart = Cart::create(['user_id' => $buyer->id, 'total_price' => 0]);
    CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => $product->price,
        'total_price' => $product->price->multiply(2),
    ]);

    $login = simulateNextRequest()
        ->postJson('/api/login', ['email' => $buyer->email, 'password' => 'password'])
        ->assertOk();

    $show = simulateNextRequest($login)->getJson('/api/cart')->assertOk();
    expect($show->json('data.items'))->toHaveCount(1);
    expect($show->json('data.items.0.quantity'))->toBe(2);
    expect($show->json('data.notice'))->toBeNull();
});

function loggedInCartBuyer(): array
{
    seedBuyerRole();
    $buyer = User::factory()->create(['password' => Hash::make('password')]);
    $buyer->assignRole(RolesEnum::ROLE_BUYER->value);

    $login = simulateNextRequest()
        ->postJson('/api/login', ['email' => $buyer->email, 'password' => 'password'])
        ->assertOk();

    return [$buyer, $login];
}

// Regression: AddToCartDto typed the price as int, so a logged-in Buyer's 19.99 item was saved as 19.
it("keeps the cents of an authenticated Buyer's cart item price", function () {
    [, $login] = loggedInCartBuyer();
    $product = Product::factory()->create(['price' => 19.99]);

    $add = simulateNextRequest($login)->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2])->assertOk();

    expect($add->json('data.items.0.unit_price'))->toBe(19.99);
    expect($add->json('data.items.0.total_price'))->toBe(39.98);
    expect(CartItem::sole())
        ->unit_price->toDecimal()->toBe('19.99')
        ->total_price->toDecimal()->toBe('39.98');
});

it('keeps the cents when an authenticated Buyer adds more of an item already in the cart', function () {
    [, $login] = loggedInCartBuyer();
    $product = Product::factory()->create(['price' => 4.35]);

    $first = simulateNextRequest($login)->postJson('/api/cart/items', ['product_id' => $product->id])->assertOk();
    $again = simulateNextRequest($first)->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2])->assertOk();

    expect($again->json('data.items'))->toHaveCount(1);
    expect($again->json('data.items.0.quantity'))->toBe(3);
    expect($again->json('data.items.0.unit_price'))->toBe(4.35);
    expect($again->json('data.items.0.total_price'))->toBe(13.05);
    expect($again->json('data.total_price'))->toBe(13.05);
});

it('keeps the cents when a guest cart is transferred to the database on login', function () {
    seedBuyerRole();
    $buyer = User::factory()->create(['password' => Hash::make('password')]);
    $buyer->assignRole(RolesEnum::ROLE_BUYER->value);
    $product = Product::factory()->create(['price' => 8.20]);

    $add = simulateNextRequest()->postJson('/api/cart/items', ['product_id' => $product->id])->assertOk();
    $login = simulateNextRequest($add)
        ->postJson('/api/login', ['email' => $buyer->email, 'password' => 'password'])
        ->assertOk();

    expect(CartItem::sole()->unit_price->toDecimal())->toBe('8.20');
    $show = simulateNextRequest($login)->getJson('/api/cart')->assertOk();
    expect($show->json('data.items.0.unit_price'))->toBe(8.2);
});
