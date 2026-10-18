<?php

use App\Enums\NotificationEventTypeEnum;
use App\Enums\OrderPaymentStatusEnum;
use App\Enums\OrderStatusEnum;
use App\Enums\RolesEnum;
use App\Models\Order;
use App\Models\StripeOrderDetail;
use App\Models\User;
use App\Notifications\OrderNotification;
use App\Services\StripeService;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Stripe\Checkout\Session;

use function Pest\Laravel\mock;

/**
 * @param  array<string, mixed>  $session  Checkout Session fields returned by retrieveSession()
 */
function fakeStripeSession(array $session): void
{
    mock(StripeService::class, function ($mock) use ($session) {
        $mock->shouldReceive('retrieveSession')
            ->andReturn(Session::constructFrom(['id' => 'cs_test_123', ...$session]));
    });
}

function stripeBuyer(): User
{
    Role::firstOrCreate(['name' => RolesEnum::ROLE_BUYER->value]);
    $buyer = User::factory()->create();
    $buyer->assignRole(RolesEnum::ROLE_BUYER->value);

    return $buyer;
}

function pendingStripeOrder(User $buyer, string $sessionId = 'cs_test_123'): Order
{
    $order = Order::factory()->create(['user_id' => $buyer->id]);
    StripeOrderDetail::create(['order_id' => $order->id, 'session_id' => $sessionId]);

    return $order;
}

function buyerWithPendingOrder(): array
{
    $buyer = stripeBuyer();

    return [$buyer, pendingStripeOrder($buyer)];
}

it('transitions the Order to Paid and notifies the Buyer on a paid Stripe session', function () {
    Notification::fake();
    fakeStripeSession(['payment_status' => 'paid']);
    [$buyer, $order] = buyerWithPendingOrder();

    $response = $this->actingAs($buyer)->getJson('/api/success?session_id=cs_test_123')->assertOk();

    expect($response->json('order_id'))->toBe($order->id);
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
});

// With the flag off the Order's Supplier is the admin who owns its products (#45).
it("notifies the Order's Supplier that it is ready to ship, whether or not suppliers are enabled", function (bool $suppliersEnabled) {
    config(['features.suppliers_enabled' => $suppliersEnabled]);
    Notification::fake();
    fakeStripeSession(['payment_status' => 'paid']);
    [$buyer, $order] = buyerWithPendingOrder();

    $this->actingAs($buyer)->getJson('/api/success?session_id=cs_test_123')->assertOk();

    Notification::assertSentTo(
        $order->fresh()->supplier,
        OrderNotification::class,
        fn ($notification, $channels, $notifiable) => $notification->toDatabase($notifiable)['event_type']
            === NotificationEventTypeEnum::ORDER_READY_FOR_SUPPLIER->value
    );
})->with([true, false]);

it('leaves the Order Pending on an unpaid session, since an async payment may still succeed', function () {
    config(['features.suppliers_enabled' => true]);
    Notification::fake();
    fakeStripeSession(['payment_status' => 'unpaid']);
    [$buyer, $order] = buyerWithPendingOrder();

    $response = $this->actingAs($buyer)->getJson('/api/success?session_id=cs_test_123')->assertOk();

    expect($response->json('payment_status'))->toBe(OrderPaymentStatusEnum::PENDING->value);
    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'order_status' => OrderStatusEnum::Draft->value,
        'payment_status' => OrderPaymentStatusEnum::PENDING->value,
    ]);
    Notification::assertNothingSent();
});

it('only sends the payment-confirmed and ready-for-supplier Notifications once across repeated success hits', function () {
    config(['features.suppliers_enabled' => true]);
    Notification::fake();
    fakeStripeSession(['payment_status' => 'paid']);
    [$buyer, $order] = buyerWithPendingOrder();

    $this->actingAs($buyer)->getJson('/api/success?session_id=cs_test_123')->assertOk();
    $this->actingAs($buyer)->getJson('/api/success?session_id=cs_test_123')->assertOk();

    Notification::assertSentToTimes($buyer->fresh(), OrderNotification::class, 1);
    Notification::assertSentToTimes($order->fresh()->supplier, OrderNotification::class, 1);
});

it('finalizes the Order matching session_id, not the Buyer\'s latest Order', function () {
    Notification::fake();
    fakeStripeSession(['payment_status' => 'paid']);
    $buyer = stripeBuyer();
    $first = pendingStripeOrder($buyer, 'cs_test_123');
    $latest = pendingStripeOrder($buyer, 'cs_test_456');
    $latest->forceFill(['created_at' => now()->addMinute()])->save();

    $response = $this->actingAs($buyer)->getJson('/api/success?session_id=cs_test_123')->assertOk();

    expect($response->json('order_id'))->toBe($first->id);
    expect($first->fresh()->payment_status)->toBe(OrderPaymentStatusEnum::PAID->value);
    expect($latest->fresh()->payment_status)->toBe(OrderPaymentStatusEnum::PENDING->value);
});

it("returns 404 for another Buyer's session_id and changes nothing", function () {
    fakeStripeSession(['payment_status' => 'paid']);
    [, $order] = buyerWithPendingOrder();

    $this->actingAs(stripeBuyer())->getJson('/api/success?session_id=cs_test_123')->assertNotFound();

    expect($order->fresh()->payment_status)->toBe(OrderPaymentStatusEnum::PENDING->value);
});

it('returns 404 for an unknown session_id', function () {
    $this->actingAs(stripeBuyer())->getJson('/api/success?session_id=cs_test_unknown')->assertNotFound();
});

it('rejects success without a session_id', function () {
    $this->actingAs(stripeBuyer())->getJson('/api/success')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('session_id');
});

it('returns the Buyer\'s Order with the open Stripe session\'s url as retry_url on cancel, without changing it', function () {
    fakeStripeSession(['status' => 'open', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_123']);
    [$buyer, $order] = buyerWithPendingOrder();

    $this->actingAs($buyer)->getJson("/api/cancel?order_id={$order->id}")
        ->assertOk()
        ->assertExactJson([
            'order_id' => $order->id,
            'order_status' => OrderStatusEnum::Draft->value,
            'payment_status' => OrderPaymentStatusEnum::PENDING->value,
            'retry_url' => 'https://checkout.stripe.com/c/pay/cs_test_123',
        ]);

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'order_status' => OrderStatusEnum::Draft->value,
        'payment_status' => OrderPaymentStatusEnum::PENDING->value,
    ]);
});

it('returns a null retry_url on cancel once the Stripe session has expired', function () {
    fakeStripeSession(['status' => 'expired', 'url' => null]);
    [$buyer, $order] = buyerWithPendingOrder();

    $this->actingAs($buyer)->getJson("/api/cancel?order_id={$order->id}")
        ->assertOk()
        ->assertJsonPath('retry_url', null);
});

it('returns a null retry_url on cancel for an Order that is no longer Pending, without calling Stripe', function () {
    mock(StripeService::class)->shouldNotReceive('retrieveSession');
    [$buyer, $order] = buyerWithPendingOrder();
    $order->update(['payment_status' => OrderPaymentStatusEnum::PAID->value]);

    $this->actingAs($buyer)->getJson("/api/cancel?order_id={$order->id}")
        ->assertOk()
        ->assertJsonPath('retry_url', null);
});

it("returns 404 on cancel for another Buyer's Order, and 422 without an order_id", function () {
    [, $order] = buyerWithPendingOrder();
    $otherBuyer = stripeBuyer();

    $this->actingAs($otherBuyer)->getJson("/api/cancel?order_id={$order->id}")->assertNotFound();
    $this->actingAs($otherBuyer)->getJson('/api/cancel')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('order_id');
});

it('rejects success and cancel for a guest', function () {
    $this->getJson('/api/success?session_id=cs_test_123')->assertUnauthorized();
    $this->getJson('/api/cancel?order_id=1')->assertUnauthorized();
});
