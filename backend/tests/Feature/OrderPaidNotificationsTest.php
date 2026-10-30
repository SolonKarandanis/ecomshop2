<?php

use App\Enums\NotificationEventTypeEnum;
use App\Enums\OrderPaymentStatusEnum;
use App\Enums\OrderStatusEnum;
use App\Enums\RolesEnum;
use App\Enums\UserStatusEnum;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderNotification;
use Filament\Actions\Testing\TestAction;
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

function applyFromOrdersList(Order $order, string $action): void
{
    Livewire::test(ListOrders::class)->callAction(TestAction::make($action)->table($order));
}

it('lets an admin ship and deliver a Cash-on-Delivery Order from the Order page, notifying the Buyer (#82)', function () {
    Notification::fake();
    $this->actingAs(paidNotificationsAdmin());
    $order = Order::factory()->cashOnDelivery()->create();

    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])->callAction('ship');
    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])->callAction('deliver');

    $order->refresh();
    expect($order->order_status)->toBe(OrderStatusEnum::Delivered->value)
        ->and($order->payment_status)->toBe(OrderPaymentStatusEnum::PAID->value);
    Notification::assertSentTo(
        $order->user,
        OrderNotification::class,
        fn ($notification, $channels, $notifiable) => $notification->toDatabase($notifiable)['event_type'] === NotificationEventTypeEnum::ORDER_DELIVERED->value
    );
});

it('sends the ready-for-supplier Notification for a new Cash-on-Delivery Order to the admin who is its Supplier when suppliers are disabled', function () {
    config(['features.suppliers_enabled' => false]);
    Notification::fake();
    $admin = paidNotificationsAdmin();

    Order::factory()->cashOnDelivery()->create(['supplier_id' => $admin->id]);

    assertSentEventTo($admin, NotificationEventTypeEnum::ORDER_READY_FOR_SUPPLIER);
});

it('offers an admin no "mark paid" action, and no status fields on the Order form (#82)', function () {
    $this->actingAs(paidNotificationsAdmin());
    $order = Order::factory()->cashOnDelivery()->create();

    Livewire::test(ListOrders::class)
        ->assertActionVisible(TestAction::make('ship')->table($order))
        ->assertActionVisible(TestAction::make('cancel')->table($order))
        ->assertActionHidden(TestAction::make('deliver')->table($order));

    Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
        ->assertFormFieldDoesNotExist('order_status')
        ->assertFormFieldDoesNotExist('payment_status');
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

it('offers no status actions on a terminal Order', function (string $state) {
    Notification::fake();
    $this->actingAs(paidNotificationsAdmin());
    $order = Order::factory()->{$state}()->create();

    $list = Livewire::test(ListOrders::class);
    foreach (['ship', 'deliver', 'cancel'] as $action) {
        $list->assertActionHidden(TestAction::make($action)->table($order));
    }

    Notification::assertNothingSent();
})->with(['delivered', 'cancelled']);

it('lets an admin ship a paid Order from the Orders list', function () {
    Notification::fake();
    $this->actingAs(paidNotificationsAdmin());
    $order = Order::factory()->paid()->create();

    applyFromOrdersList($order, 'ship');

    expect($order->fresh()->order_status)->toBe(OrderStatusEnum::Shipped->value);
    assertSentEventTo($order->user, NotificationEventTypeEnum::ORDER_SHIPPED);
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
