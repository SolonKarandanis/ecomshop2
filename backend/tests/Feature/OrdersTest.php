<?php

use App\Enums\OrderStatusEnum;
use App\Enums\RolesEnum;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Spatie\Permission\Models\Role;

function buyer(): User
{
    Role::firstOrCreate(['name' => RolesEnum::ROLE_BUYER->value]);
    $buyer = User::factory()->create();
    $buyer->assignRole(RolesEnum::ROLE_BUYER->value);

    return $buyer;
}

function admin(): User
{
    Role::firstOrCreate(['name' => RolesEnum::ROLE_ADMIN->value]);
    $admin = User::factory()->create();
    $admin->assignRole(RolesEnum::ROLE_ADMIN->value);

    return $admin;
}

function supplier(): User
{
    Role::firstOrCreate(['name' => RolesEnum::ROLE_SUPPLIER->value]);
    $supplier = User::factory()->create();
    $supplier->assignRole(RolesEnum::ROLE_SUPPLIER->value);

    return $supplier;
}

it("lists only the authenticated Buyer's own Orders", function () {
    $buyer = buyer();
    $otherBuyer = buyer();
    Order::factory()->create(['user_id' => $buyer->id]);
    Order::factory()->count(2)->create(['user_id' => $otherBuyer->id]);

    $response = $this->actingAs($buyer)->getJson('/orders')->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBeInt();
});

it('filters the Buyer order list by order status', function () {
    $buyer = buyer();
    Order::factory()->create(['user_id' => $buyer->id, 'order_status' => OrderStatusEnum::Paid->value]);
    Order::factory()->create(['user_id' => $buyer->id, 'order_status' => OrderStatusEnum::Draft->value]);

    $response = $this->actingAs($buyer)
        ->getJson('/orders?orderStatus='.urlencode(OrderStatusEnum::Paid->value))
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.order_status'))->toBe(OrderStatusEnum::Paid->value);
});

it('rejects an invalid sortColumn on the order list', function () {
    $buyer = buyer();

    $this->actingAs($buyer)->getJson('/orders?sortColumn=notacolumn')->assertUnprocessable();
});

it("returns the owning Buyer's Order detail with items and address, without exposing the supplier id", function () {
    $buyer = buyer();
    $supplier = supplier();
    $order = Order::factory()->create(['user_id' => $buyer->id, 'supplier_id' => $supplier->id]);
    $product = Product::factory()->create(['supplier_id' => $supplier->id]);
    $order->items()->create([
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_amount' => 10,
        'total_amount' => 20,
    ]);
    Address::create([
        'user_id' => $buyer->id,
        'order_id' => $order->id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'phone' => '555-1234',
        'street_address' => '1 Main St',
        'city' => 'Athens',
        'country' => 'Greece',
        'postal_code' => '12345',
    ]);

    $response = $this->actingAs($buyer)->getJson("/orders/{$order->id}")->assertOk();

    expect($response->json('data.id'))->toBe($order->id);
    expect($response->json('data.items'))->toHaveCount(1);
    expect($response->json('data.items.0.product_id'))->toBe($product->id);
    expect($response->json('data.address.city'))->toBe('Athens');
    expect($response->json('data'))->not->toHaveKey('supplier_id');
});

it("rejects a Buyer requesting another Buyer's Order with 403", function () {
    $buyer = buyer();
    $otherBuyer = buyer();
    $order = Order::factory()->create(['user_id' => $otherBuyer->id]);

    $this->actingAs($buyer)->getJson("/orders/{$order->id}")->assertForbidden();
});

it('lets an Admin view any Order, including the supplier id', function () {
    $admin = admin();
    $buyer = buyer();
    $supplier = supplier();
    $order = Order::factory()->create(['user_id' => $buyer->id, 'supplier_id' => $supplier->id]);

    $response = $this->actingAs($admin)->getJson("/orders/{$order->id}")->assertOk();

    expect($response->json('data.supplier_id'))->toBe($supplier->id);
});

it('lets the owning Supplier view the Order, including the supplier id', function () {
    $buyer = buyer();
    $supplier = supplier();
    $order = Order::factory()->create(['user_id' => $buyer->id, 'supplier_id' => $supplier->id]);

    $response = $this->actingAs($supplier)->getJson("/orders/{$order->id}")->assertOk();

    expect($response->json('data.supplier_id'))->toBe($supplier->id);
});

it('rejects a Supplier viewing an Order it does not fulfil', function () {
    $buyer = buyer();
    $supplier = supplier();
    $otherSupplier = supplier();
    $order = Order::factory()->create(['user_id' => $buyer->id, 'supplier_id' => $supplier->id]);

    $this->actingAs($otherSupplier)->getJson("/orders/{$order->id}")->assertForbidden();
});

it('rejects a guest from listing or viewing Orders', function () {
    $order = Order::factory()->create();

    $this->getJson('/orders')->assertUnauthorized();
    $this->getJson("/orders/{$order->id}")->assertUnauthorized();
});

it('returns 404 for a nonexistent Order', function () {
    $buyer = buyer();

    $this->actingAs($buyer)->getJson('/orders/999999')->assertNotFound();
});
