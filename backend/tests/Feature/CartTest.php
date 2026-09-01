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

    app()->forgetInstance(CartService::class);
    app('auth')->forgetGuards();

    return $client;
}

function seedBuyerRole(): void
{
    Role::firstOrCreate(['name' => RolesEnum::ROLE_BUYER->value]);
}

it('lets a guest add a product to the cart and persists it across requests via cookies', function () {
    $product = Product::factory()->create();

    $add = simulateNextRequest()->postJson('/cart/items', [
        'product_id' => $product->id,
        'quantity' => 2,
    ])->assertOk();

    expect($add->json('data.items'))->toHaveCount(1);
    $add->assertCookie('cart')->assertCookie('cartItems');

    $show = simulateNextRequest($add)->getJson('/cart')->assertOk();
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
        ->postJson('/login', ['email' => $buyer->email, 'password' => 'password'])
        ->assertOk();

    $add = simulateNextRequest($login)->postJson('/cart/items', [
        'product_id' => $product->id,
        'quantity' => 3,
    ])->assertOk();

    $this->assertDatabaseHas('cart_items', [
        'product_id' => $product->id,
        'quantity' => 3,
    ]);

    $show = simulateNextRequest($add)->getJson('/cart')->assertOk();
    expect($show->json('data.items.0.product_id'))->toBe($product->id);
});

it('rejects adding a product from a second Supplier to a non-empty guest cart', function () {
    $supplierA = User::factory()->create();
    $supplierB = User::factory()->create();
    $productA = Product::factory()->create(['supplier_id' => $supplierA->id]);
    $productB = Product::factory()->create(['supplier_id' => $supplierB->id]);

    $add = simulateNextRequest()->postJson('/cart/items', ['product_id' => $productA->id])->assertOk();

    $reject = simulateNextRequest($add)->postJson('/cart/items', ['product_id' => $productB->id])
        ->assertBadRequest();
    expect($reject->json('message'))->toBe(CartException::supplierMismatch()->getMessage());

    $show = simulateNextRequest($reject)->getJson('/cart')->assertOk();
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
        ->postJson('/login', ['email' => $buyer->email, 'password' => 'password'])
        ->assertOk();

    $add = simulateNextRequest($login)->postJson('/cart/items', ['product_id' => $productA->id])->assertOk();

    simulateNextRequest($add)->postJson('/cart/items', ['product_id' => $productB->id])
        ->assertBadRequest();

    $this->assertDatabaseMissing('cart_items', ['product_id' => $productB->id]);
});

it('transfers the guest cart to the database on login (TransferGuestCartToUser)', function () {
    seedBuyerRole();
    $buyer = User::factory()->create(['password' => Hash::make('password')]);
    $buyer->assignRole(RolesEnum::ROLE_BUYER->value);
    $product = Product::factory()->create();

    $add = simulateNextRequest()->postJson('/cart/items', [
        'product_id' => $product->id,
        'quantity' => 4,
    ])->assertOk();

    $login = simulateNextRequest($add)
        ->postJson('/login', ['email' => $buyer->email, 'password' => 'password'])
        ->assertOk();

    $this->assertDatabaseHas('cart_items', [
        'product_id' => $product->id,
        'quantity' => 4,
    ]);

    $show = simulateNextRequest($login)->getJson('/cart')->assertOk();
    expect($show->json('data.items'))->toHaveCount(1);
    expect($show->json('data.items.0.quantity'))->toBe(4);
});

it('updates a guest cart item quantity', function () {
    $product = Product::factory()->create();

    $add = simulateNextRequest()->postJson('/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertOk();
    $itemId = $add->json('data.items.0.id');

    $update = simulateNextRequest($add)->patchJson("/cart/items/{$itemId}", ['quantity' => 5])->assertOk();

    expect($update->json('data.items.0.quantity'))->toBe(5);
});

it('updates an authenticated Buyer cart item quantity', function () {
    seedBuyerRole();
    $buyer = User::factory()->create(['password' => Hash::make('password')]);
    $buyer->assignRole(RolesEnum::ROLE_BUYER->value);
    $product = Product::factory()->create();

    $login = simulateNextRequest()
        ->postJson('/login', ['email' => $buyer->email, 'password' => 'password'])
        ->assertOk();

    $add = simulateNextRequest($login)->postJson('/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertOk();
    $itemId = $add->json('data.items.0.id');

    simulateNextRequest($add)->patchJson("/cart/items/{$itemId}", ['quantity' => 7])->assertOk();

    $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 7]);
});

it('removes a guest cart item', function () {
    $product = Product::factory()->create();

    $add = simulateNextRequest()->postJson('/cart/items', ['product_id' => $product->id])->assertOk();
    $itemId = $add->json('data.items.0.id');

    $remove = simulateNextRequest($add)->deleteJson("/cart/items/{$itemId}")->assertOk();

    expect($remove->json('data.items'))->toHaveCount(0);
});

it('removes an authenticated Buyer cart item', function () {
    seedBuyerRole();
    $buyer = User::factory()->create(['password' => Hash::make('password')]);
    $buyer->assignRole(RolesEnum::ROLE_BUYER->value);
    $product = Product::factory()->create();

    $login = simulateNextRequest()
        ->postJson('/login', ['email' => $buyer->email, 'password' => 'password'])
        ->assertOk();

    $add = simulateNextRequest($login)->postJson('/cart/items', ['product_id' => $product->id])->assertOk();
    $itemId = $add->json('data.items.0.id');

    simulateNextRequest($add)->deleteJson("/cart/items/{$itemId}")->assertOk();

    $this->assertDatabaseMissing('cart_items', ['product_id' => $product->id]);
});

it('rejects adding to cart for an authenticated Supplier', function () {
    Role::firstOrCreate(['name' => RolesEnum::ROLE_SUPPLIER->value]);
    $supplier = User::factory()->create(['password' => Hash::make('password')]);
    $supplier->assignRole(RolesEnum::ROLE_SUPPLIER->value);
    $product = Product::factory()->create();

    $login = simulateNextRequest()
        ->postJson('/login', ['email' => $supplier->email, 'password' => 'password'])
        ->assertOk();

    simulateNextRequest($login)->postJson('/cart/items', ['product_id' => $product->id])->assertForbidden();
});

it('returns an empty cart for a fresh guest', function () {
    simulateNextRequest()
        ->getJson('/cart')
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
        'total_price' => $product->price * 2,
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
