<?php

use App\Enums\NotificationEventTypeEnum;
use App\Enums\OrderStatusEnum;
use App\Enums\RolesEnum;
use App\Enums\UserStatusEnum;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function paidNotificationsAdmin(): User
{
    Role::firstOrCreate(['name' => RolesEnum::ROLE_ADMIN->value]);
    $admin = User::factory()->create(['status' => UserStatusEnum::ACTIVE->value]);
    $admin->assignRole(RolesEnum::ROLE_ADMIN->value);

    return $admin;
}

function assertSentEventTo(User $user, NotificationEventTypeEnum $eventType, int $times = 1): void
{
    Notification::assertSentToTimes($user, OrderNotification::class, $times);
    Notification::assertSentTo(
        $user,
        OrderNotification::class,
        fn ($notification, $channels, $notifiable) => $notification->toDatabase($notifiable)['event_type'] === $eventType->value
    );
}

function changeStatusFromOrdersList(Order $order, OrderStatusEnum $status): void
{
    Livewire::test(ListOrders::class)
        ->call('updateTableColumnState', 'order_status', (string) $order->getKey(), $status->value);
}

it('notifies the Buyer and Supplier when an admin marks a COD Order Paid from the edit form', function () {
    Notification::fake();
    $this->actingAs(paidNotificationsAdmin());
    $order = Order::factory()->create();

    Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
        ->fillForm(['order_status' => OrderStatusEnum::Paid->value])
        ->call('save')
        ->assertHasNoFormErrors();

    // EditOrder re-resolves supplier_id from the items (the admin, when there are none).
    $order->refresh();
    expect($order->order_status)->toBe(OrderStatusEnum::Paid->value);
    assertSentEventTo($order->user, NotificationEventTypeEnum::ORDER_PAYMENT_CONFIRMED);
    assertSentEventTo($order->supplier, NotificationEventTypeEnum::ORDER_READY_FOR_SUPPLIER);
});

it('notifies the Buyer and Supplier when an admin marks a COD Order Paid from the Orders list', function () {
    Notification::fake();
    $this->actingAs(paidNotificationsAdmin());
    $order = Order::factory()->create();

    changeStatusFromOrdersList($order, OrderStatusEnum::Paid);

    expect($order->fresh()->order_status)->toBe(OrderStatusEnum::Paid->value);
    assertSentEventTo($order->user, NotificationEventTypeEnum::ORDER_PAYMENT_CONFIRMED);
    assertSentEventTo($order->supplier, NotificationEventTypeEnum::ORDER_READY_FOR_SUPPLIER);
});

it('sends the ready-for-supplier Notification to the admin who is the Order\'s Supplier when suppliers are disabled', function () {
    config(['features.suppliers_enabled' => false]);
    Notification::fake();
    $admin = paidNotificationsAdmin();
    $this->actingAs($admin);
    $order = Order::factory()->create(['supplier_id' => $admin->id]);

    changeStatusFromOrdersList($order, OrderStatusEnum::Paid);

    assertSentEventTo($admin, NotificationEventTypeEnum::ORDER_READY_FOR_SUPPLIER);
});

it('sends no Notifications when an Order is saved without a status change', function () {
    Notification::fake();
    $this->actingAs(paidNotificationsAdmin());
    $order = Order::factory()->create(['order_status' => OrderStatusEnum::Paid->value]);

    Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
        ->fillForm(['notes' => 'Leave at the door'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($order->fresh()->notes)->toBe('Leave at the door');
    Notification::assertNothingSent();
});

it("doesn't let a terminal Order's status change from the Orders list", function (OrderStatusEnum $terminal) {
    Notification::fake();
    $this->actingAs(paidNotificationsAdmin());
    $order = Order::factory()->create(['order_status' => $terminal->value]);

    changeStatusFromOrdersList($order, OrderStatusEnum::Draft);

    expect($order->fresh()->order_status)->toBe($terminal->value);
    Notification::assertNothingSent();
})->with([OrderStatusEnum::Delivered, OrderStatusEnum::Cancelled]);

it('still lets a non-terminal Order\'s status change from the Orders list', function () {
    Notification::fake();
    $this->actingAs(paidNotificationsAdmin());
    $order = Order::factory()->create(['order_status' => OrderStatusEnum::Paid->value]);

    changeStatusFromOrdersList($order, OrderStatusEnum::Shipped);

    expect($order->fresh()->order_status)->toBe(OrderStatusEnum::Shipped->value);
});

it('sends no Notifications for a status change whose transaction rolls back', function () {
    Notification::fake();
    $order = Order::factory()->create();

    try {
        DB::transaction(function () use ($order) {
            $order->update(['order_status' => OrderStatusEnum::Paid->value]);
            throw new RuntimeException('rolled back');
        });
    } catch (RuntimeException) {
    }

    expect($order->fresh()->order_status)->toBe(OrderStatusEnum::Draft->value);
    Notification::assertNothingSent();
});
