<?php

use App\Enums\NotificationEventTypeEnum;
use App\Enums\OrderStatusEnum;
use App\Enums\RolesEnum;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

function supplierActionsBuyer(): User
{
    Role::firstOrCreate(['name' => RolesEnum::ROLE_BUYER->value]);
    $buyer = User::factory()->create();
    $buyer->assignRole(RolesEnum::ROLE_BUYER->value);

    return $buyer;
}

function supplierActionsAdmin(): User
{
    Role::firstOrCreate(['name' => RolesEnum::ROLE_ADMIN->value]);
    $admin = User::factory()->create();
    $admin->assignRole(RolesEnum::ROLE_ADMIN->value);

    return $admin;
}

function supplierActionsSupplier(): User
{
    Role::firstOrCreate(['name' => RolesEnum::ROLE_SUPPLIER->value]);
    $supplier = User::factory()->create();
    $supplier->assignRole(RolesEnum::ROLE_SUPPLIER->value);

    return $supplier;
}

beforeEach(function () {
    config(['features.suppliers_enabled' => true]);
});

it('lets a Supplier ship their own Paid Order and notifies the Buyer', function () {
    Notification::fake();
    $supplier = supplierActionsSupplier();
    $buyer = supplierActionsBuyer();
    $order = Order::factory()->create(['user_id' => $buyer->id, 'supplier_id' => $supplier->id, 'order_status' => OrderStatusEnum::Paid->value]);

    $response = $this->actingAs($supplier)->postJson("/supplier-orders/{$order->id}/ship")->assertOk();

    expect($response->json('data.order_status'))->toBe(OrderStatusEnum::Shipped->value);
    $this->assertDatabaseHas('orders', ['id' => $order->id, 'order_status' => OrderStatusEnum::Shipped->value]);
    Notification::assertSentTo(
        $buyer->fresh(),
        OrderNotification::class,
        fn ($notification, $channels, $notifiable) => $notification->toDatabase($notifiable)['event_type']
            === NotificationEventTypeEnum::ORDER_SHIPPED->value
    );
});

it('lets a Supplier cancel their own Paid Order and notifies the Buyer', function () {
    Notification::fake();
    $supplier = supplierActionsSupplier();
    $buyer = supplierActionsBuyer();
    $order = Order::factory()->create(['user_id' => $buyer->id, 'supplier_id' => $supplier->id, 'order_status' => OrderStatusEnum::Paid->value]);

    $response = $this->actingAs($supplier)->postJson("/supplier-orders/{$order->id}/cancel")->assertOk();

    expect($response->json('data.order_status'))->toBe(OrderStatusEnum::Cancelled->value);
    Notification::assertSentTo(
        $buyer->fresh(),
        OrderNotification::class,
        fn ($notification, $channels, $notifiable) => $notification->toDatabase($notifiable)['event_type']
            === NotificationEventTypeEnum::ORDER_CANCELLED->value
    );
});

it('lets a Supplier deliver their own Shipped Order and notifies the Buyer', function () {
    Notification::fake();
    $supplier = supplierActionsSupplier();
    $buyer = supplierActionsBuyer();
    $order = Order::factory()->create(['user_id' => $buyer->id, 'supplier_id' => $supplier->id, 'order_status' => OrderStatusEnum::Shipped->value]);

    $response = $this->actingAs($supplier)->postJson("/supplier-orders/{$order->id}/deliver")->assertOk();

    expect($response->json('data.order_status'))->toBe(OrderStatusEnum::Delivered->value);
    Notification::assertSentTo(
        $buyer->fresh(),
        OrderNotification::class,
        fn ($notification, $channels, $notifiable) => $notification->toDatabase($notifiable)['event_type']
            === NotificationEventTypeEnum::ORDER_DELIVERED->value
    );
});

it('rejects delivering a still-Paid Order as an illegal transition', function () {
    $supplier = supplierActionsSupplier();
    $order = Order::factory()->create(['supplier_id' => $supplier->id, 'order_status' => OrderStatusEnum::Paid->value]);

    $this->actingAs($supplier)->postJson("/supplier-orders/{$order->id}/deliver")->assertStatus(400);

    $this->assertDatabaseHas('orders', ['id' => $order->id, 'order_status' => OrderStatusEnum::Paid->value]);
});

it('rejects shipping an already-Shipped Order as an illegal transition', function () {
    $supplier = supplierActionsSupplier();
    $order = Order::factory()->create(['supplier_id' => $supplier->id, 'order_status' => OrderStatusEnum::Shipped->value]);

    $this->actingAs($supplier)->postJson("/supplier-orders/{$order->id}/ship")->assertStatus(400);
});

it('rejects acting on a terminal Delivered Order for every role, including Admin', function () {
    $supplier = supplierActionsSupplier();
    $admin = supplierActionsAdmin();
    $order = Order::factory()->create(['supplier_id' => $supplier->id, 'order_status' => OrderStatusEnum::Delivered->value]);

    $this->actingAs($supplier)->postJson("/supplier-orders/{$order->id}/ship")->assertForbidden();
    $this->actingAs($admin)->postJson("/supplier-orders/{$order->id}/ship")->assertForbidden();
});

it('rejects acting on a terminal Cancelled Order', function () {
    $supplier = supplierActionsSupplier();
    $order = Order::factory()->create(['supplier_id' => $supplier->id, 'order_status' => OrderStatusEnum::Cancelled->value]);

    $this->actingAs($supplier)->postJson("/supplier-orders/{$order->id}/deliver")->assertForbidden();
});

it("rejects a Supplier acting on an Order that doesn't contain their Products", function () {
    $supplier = supplierActionsSupplier();
    $otherSupplier = supplierActionsSupplier();
    $order = Order::factory()->create(['supplier_id' => $otherSupplier->id, 'order_status' => OrderStatusEnum::Paid->value]);

    $this->actingAs($supplier)->postJson("/supplier-orders/{$order->id}/ship")->assertForbidden();
});

it('rejects a Buyer performing a Supplier order action', function () {
    $buyer = supplierActionsBuyer();
    $order = Order::factory()->create(['user_id' => $buyer->id, 'order_status' => OrderStatusEnum::Paid->value]);

    $this->actingAs($buyer)->postJson("/supplier-orders/{$order->id}/ship")->assertForbidden();
});

it('rejects an Admin performing a Supplier order action', function () {
    $admin = supplierActionsAdmin();
    $order = Order::factory()->create(['order_status' => OrderStatusEnum::Paid->value]);

    $this->actingAs($admin)->postJson("/supplier-orders/{$order->id}/ship")->assertForbidden();
});

it('rejects a guest performing a Supplier order action', function () {
    $order = Order::factory()->create(['order_status' => OrderStatusEnum::Paid->value]);

    $this->postJson("/supplier-orders/{$order->id}/ship")->assertUnauthorized();
});

it('rejects a Supplier order action when the Suppliers Feature is off', function () {
    config(['features.suppliers_enabled' => false]);
    $supplier = supplierActionsSupplier();
    $order = Order::factory()->create(['supplier_id' => $supplier->id, 'order_status' => OrderStatusEnum::Paid->value]);

    $this->actingAs($supplier)->postJson("/supplier-orders/{$order->id}/ship")->assertForbidden();
});

it('returns 404 for a nonexistent Order on a Supplier action', function () {
    $supplier = supplierActionsSupplier();

    $this->actingAs($supplier)->postJson('/supplier-orders/999999/ship')->assertNotFound();
});
