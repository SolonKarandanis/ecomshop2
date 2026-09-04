<?php

use App\Enums\NotificationEventTypeEnum;
use App\Enums\OrderPaymentStatusEnum;
use App\Enums\OrderStatusEnum;
use App\Enums\RolesEnum;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderNotification;
use App\Services\StripeService;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Stripe\Checkout\Session;

use function Pest\Laravel\mock;

function fakeStripeSession(string $paymentStatus): void
{
    mock(StripeService::class, function ($mock) use ($paymentStatus) {
        $mock->shouldReceive('retrieveSession')
            ->andReturn(Session::constructFrom(['id' => 'cs_test_123', 'payment_status' => $paymentStatus]));
    });
}

function buyerWithPendingOrder(): array
{
    Role::firstOrCreate(['name' => RolesEnum::ROLE_BUYER->value]);
    $buyer = User::factory()->create();
    $buyer->assignRole(RolesEnum::ROLE_BUYER->value);
    $order = Order::factory()->create(['user_id' => $buyer->id]);

    return [$buyer, $order];
}

it('transitions the Order to Paid and notifies the Buyer on a paid Stripe session', function () {
    Notification::fake();
    fakeStripeSession('paid');
    [$buyer, $order] = buyerWithPendingOrder();

    $response = $this->actingAs($buyer)->getJson('/success?session_id=cs_test_123')->assertOk();

    expect($response->json('order_status'))->toBe(OrderStatusEnum::Paid->value);
    expect($response->json('payment_status'))->toBe(OrderPaymentStatusEnum::PAID->value);
    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'order_status' => OrderStatusEnum::Paid->value,
        'payment_status' => OrderPaymentStatusEnum::PAID->value,
    ]);

    Notification::assertSentTo(
        $buyer->fresh(),
        OrderNotification::class,
        fn ($notification, $channels, $notifiable) => $notification->toDatabase($notifiable)['event_type']
            === NotificationEventTypeEnum::ORDER_PAYMENT_CONFIRMED->value
    );
    Notification::assertNotSentTo($order->fresh()->supplier, OrderNotification::class);
});

it('notifies the Supplier only when suppliers are enabled', function () {
    config(['features.suppliers_enabled' => true]);
    Notification::fake();
    fakeStripeSession('paid');
    [$buyer, $order] = buyerWithPendingOrder();

    $this->actingAs($buyer)->getJson('/success?session_id=cs_test_123')->assertOk();

    Notification::assertSentTo(
        $order->fresh()->supplier,
        OrderNotification::class,
        fn ($notification, $channels, $notifiable) => $notification->toDatabase($notifiable)['event_type']
            === NotificationEventTypeEnum::ORDER_READY_FOR_SUPPLIER->value
    );
});

it('marks the Order payment Failed and notifies the Buyer on an unpaid Stripe session, without notifying the Supplier', function () {
    config(['features.suppliers_enabled' => true]);
    Notification::fake();
    fakeStripeSession('unpaid');
    [$buyer, $order] = buyerWithPendingOrder();

    $response = $this->actingAs($buyer)->getJson('/success?session_id=cs_test_123')->assertOk();

    expect($response->json('payment_status'))->toBe(OrderPaymentStatusEnum::FAILED->value);
    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'order_status' => OrderStatusEnum::Draft->value,
        'payment_status' => OrderPaymentStatusEnum::FAILED->value,
    ]);

    Notification::assertSentTo(
        $buyer->fresh(),
        OrderNotification::class,
        fn ($notification, $channels, $notifiable) => $notification->toDatabase($notifiable)['event_type']
            === NotificationEventTypeEnum::ORDER_PAYMENT_FAILED->value
    );
    Notification::assertNotSentTo($order->fresh()->supplier, OrderNotification::class);
});

it('only sends the payment-confirmed and ready-for-supplier Notifications once across repeated success hits', function () {
    config(['features.suppliers_enabled' => true]);
    Notification::fake();
    fakeStripeSession('paid');
    [$buyer, $order] = buyerWithPendingOrder();

    $this->actingAs($buyer)->getJson('/success?session_id=cs_test_123')->assertOk();
    $this->actingAs($buyer)->getJson('/success?session_id=cs_test_123')->assertOk();

    Notification::assertSentToTimes($buyer->fresh(), OrderNotification::class, 1);
    Notification::assertSentToTimes($order->fresh()->supplier, OrderNotification::class, 1);
});

it('does not alter Order or Payment Status on cancel', function () {
    [$buyer, $order] = buyerWithPendingOrder();

    $response = $this->actingAs($buyer)->getJson('/cancel')->assertOk();

    expect($response->json('order_status'))->toBe(OrderStatusEnum::Draft->value);
    expect($response->json('payment_status'))->toBe(OrderPaymentStatusEnum::PENDING->value);
    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'order_status' => OrderStatusEnum::Draft->value,
        'payment_status' => OrderPaymentStatusEnum::PENDING->value,
    ]);
});

it('rejects success and cancel for a guest', function () {
    $this->getJson('/success')->assertUnauthorized();
    $this->getJson('/cancel')->assertUnauthorized();
});
