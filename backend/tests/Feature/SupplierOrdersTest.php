<?php

use App\Enums\OrderStatusEnum;
use App\Enums\RolesEnum;
use App\Models\Order;
use App\Models\User;
use Spatie\Permission\Models\Role;

function supplierOrdersBuyer(): User
{
    Role::firstOrCreate(['name' => RolesEnum::ROLE_BUYER->value]);
    $buyer = User::factory()->create();
    $buyer->assignRole(RolesEnum::ROLE_BUYER->value);

    return $buyer;
}

function supplierOrdersAdmin(): User
{
    Role::firstOrCreate(['name' => RolesEnum::ROLE_ADMIN->value]);
    $admin = User::factory()->create();
    $admin->assignRole(RolesEnum::ROLE_ADMIN->value);

    return $admin;
}

function supplierOrdersSupplier(): User
{
    Role::firstOrCreate(['name' => RolesEnum::ROLE_SUPPLIER->value]);
    $supplier = User::factory()->create();
    $supplier->assignRole(RolesEnum::ROLE_SUPPLIER->value);

    return $supplier;
}

beforeEach(function () {
    config(['features.suppliers_enabled' => true]);
});

it("lists only the authenticated Supplier's own Orders, excluding Draft", function () {
    $supplier = supplierOrdersSupplier();
    $otherSupplier = supplierOrdersSupplier();
    $buyer = supplierOrdersBuyer();

    Order::factory()->create(['user_id' => $buyer->id, 'supplier_id' => $supplier->id, 'order_status' => OrderStatusEnum::Paid->value]);
    Order::factory()->create(['user_id' => $buyer->id, 'supplier_id' => $supplier->id, 'order_status' => OrderStatusEnum::Draft->value]);
    Order::factory()->create(['user_id' => $buyer->id, 'supplier_id' => $otherSupplier->id, 'order_status' => OrderStatusEnum::Paid->value]);

    $response = $this->actingAs($supplier)->getJson('/supplier-orders')->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.order_status'))->toBe(OrderStatusEnum::Paid->value);
});

it('includes terminal Order statuses in the Supplier order list', function () {
    $supplier = supplierOrdersSupplier();
    $buyer = supplierOrdersBuyer();

    Order::factory()->create(['user_id' => $buyer->id, 'supplier_id' => $supplier->id, 'order_status' => OrderStatusEnum::Delivered->value]);
    Order::factory()->create(['user_id' => $buyer->id, 'supplier_id' => $supplier->id, 'order_status' => OrderStatusEnum::Cancelled->value]);

    $response = $this->actingAs($supplier)->getJson('/supplier-orders')->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

it('filters the Supplier order list by order status', function () {
    $supplier = supplierOrdersSupplier();
    $buyer = supplierOrdersBuyer();

    Order::factory()->create(['user_id' => $buyer->id, 'supplier_id' => $supplier->id, 'order_status' => OrderStatusEnum::Paid->value]);
    Order::factory()->create(['user_id' => $buyer->id, 'supplier_id' => $supplier->id, 'order_status' => OrderStatusEnum::Shipped->value]);

    $response = $this->actingAs($supplier)
        ->getJson('/supplier-orders?orderStatus='.urlencode(OrderStatusEnum::Paid->value))
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.order_status'))->toBe(OrderStatusEnum::Paid->value);
});

it('rejects a Buyer listing Supplier Orders with 403', function () {
    $buyer = supplierOrdersBuyer();

    $this->actingAs($buyer)->getJson('/supplier-orders')->assertForbidden();
});

it('rejects an Admin listing Supplier Orders with 403', function () {
    $admin = supplierOrdersAdmin();

    $this->actingAs($admin)->getJson('/supplier-orders')->assertForbidden();
});

it('rejects a guest listing Supplier Orders with 401', function () {
    $this->getJson('/supplier-orders')->assertUnauthorized();
});

it('rejects a Supplier listing Supplier Orders when the Suppliers Feature is off', function () {
    config(['features.suppliers_enabled' => false]);
    $supplier = supplierOrdersSupplier();

    $this->actingAs($supplier)->getJson('/supplier-orders')->assertForbidden();
});

it('checks the Suppliers Feature flag live rather than cached', function () {
    $supplier = supplierOrdersSupplier();

    config(['features.suppliers_enabled' => false]);
    $this->actingAs($supplier)->getJson('/supplier-orders')->assertForbidden();

    config(['features.suppliers_enabled' => true]);
    $this->actingAs($supplier)->getJson('/supplier-orders')->assertOk();
});
