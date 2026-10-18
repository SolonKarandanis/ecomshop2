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
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use Stripe\Checkout\Session;
use Stripe\WebhookSignature;

use function Pest\Laravel\mock;

const WEBHOOK_TEST_SECRET = 'whsec_test_secret';

beforeEach(function () {
    config(['app.stripe_webhook_secret' => WEBHOOK_TEST_SECRET]);
});

/**
 * @param  array<string, mixed>  $session  Checkout Session fields
 */
function stripeEventPayload(string $type, array $session = []): string
{
    return json_encode([
        'id' => 'evt_test_123',
        'object' => 'event',
        'type' => $type,
        'data' => ['object' => ['id' => 'cs_test_123', 'object' => 'checkout.session', ...$session]],
    ]);
}

function postStripeWebhook(string $payload, ?string $signature = null): TestResponse
{
    $signature ??= WebhookSignature::generateSignatureHeader($payload, WEBHOOK_TEST_SECRET);

    return test()->call(
        'POST',
        '/api/stripe/webhook',
        server: ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $signature],
        content: $payload,
    );
}

function webhookOrder(): Order
{
    Role::firstOrCreate(['name' => RolesEnum::ROLE_BUYER->value]);
    $buyer = User::factory()->create();
    $buyer->assignRole(RolesEnum::ROLE_BUYER->value);
    $order = Order::factory()->create(['user_id' => $buyer->id]);
    StripeOrderDetail::create(['order_id' => $order->id, 'session_id' => 'cs_test_123']);

    return $order;
}

function assertNotifiedOf(User $user, NotificationEventTypeEnum $eventType, int $times = 1): void
{
    Notification::assertSentToTimes(
        $user,
        OrderNotification::class,
        $times,
    );
    Notification::assertSentTo(
        $user,
        OrderNotification::class,
        fn ($notification, $channels, $notifiable) => $notification->toDatabase($notifiable)['event_type'] === $eventType->value
    );
}

it('marks the Order Paid and notifies the Buyer and Supplier on checkout.session.completed, without the redirect', function () {
    config(['features.suppliers_enabled' => true]);
    Notification::fake();
    $order = webhookOrder();

    postStripeWebhook(stripeEventPayload('checkout.session.completed', ['payment_status' => 'paid']))
        ->assertOk()
        ->assertExactJson(['received' => true]);

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'order_status' => OrderStatusEnum::Paid->value,
        'payment_status' => OrderPaymentStatusEnum::PAID->value,
    ]);
    assertNotifiedOf($order->user, NotificationEventTypeEnum::ORDER_PAYMENT_CONFIRMED);
    assertNotifiedOf($order->supplier, NotificationEventTypeEnum::ORDER_READY_FOR_SUPPLIER);
});

it('leaves the Order Pending on checkout.session.completed with an unpaid (async) payment', function () {
    Notification::fake();
    $order = webhookOrder();

    postStripeWebhook(stripeEventPayload('checkout.session.completed', ['payment_status' => 'unpaid']))->assertOk();

    expect($order->fresh()->payment_status)->toBe(OrderPaymentStatusEnum::PENDING->value);
    Notification::assertNothingSent();
});

it('marks the Order Paid on checkout.session.async_payment_succeeded', function () {
    Notification::fake();
    $order = webhookOrder();

    postStripeWebhook(stripeEventPayload('checkout.session.async_payment_succeeded', ['payment_status' => 'paid']))->assertOk();

    expect($order->fresh())
        ->payment_status->toBe(OrderPaymentStatusEnum::PAID->value)
        ->order_status->toBe(OrderStatusEnum::Paid->value);
    assertNotifiedOf($order->user, NotificationEventTypeEnum::ORDER_PAYMENT_CONFIRMED);
});

it('marks the payment Failed and notifies the Buyer on checkout.session.async_payment_failed', function () {
    config(['features.suppliers_enabled' => true]);
    Notification::fake();
    $order = webhookOrder();

    postStripeWebhook(stripeEventPayload('checkout.session.async_payment_failed', ['payment_status' => 'unpaid']))->assertOk();

    expect($order->fresh())
        ->payment_status->toBe(OrderPaymentStatusEnum::FAILED->value)
        ->order_status->toBe(OrderStatusEnum::Draft->value);
    assertNotifiedOf($order->user, NotificationEventTypeEnum::ORDER_PAYMENT_FAILED);
    Notification::assertNotSentTo($order->supplier, OrderNotification::class);
});

it('fails and cancels a Pending Order on checkout.session.expired, notifying the Buyer once', function () {
    Notification::fake();
    $order = webhookOrder();
    $payload = stripeEventPayload('checkout.session.expired', ['status' => 'expired', 'payment_status' => 'unpaid']);

    postStripeWebhook($payload)->assertOk();
    postStripeWebhook($payload)->assertOk();

    expect($order->fresh())
        ->payment_status->toBe(OrderPaymentStatusEnum::FAILED->value)
        ->order_status->toBe(OrderStatusEnum::Cancelled->value);
    assertNotifiedOf($order->user, NotificationEventTypeEnum::ORDER_CANCELLED);
});

it('ignores checkout.session.expired for an Order that is already Paid', function () {
    $order = webhookOrder();
    $order->update([
        'payment_status' => OrderPaymentStatusEnum::PAID->value,
        'order_status' => OrderStatusEnum::Paid->value,
    ]);
    Notification::fake();

    postStripeWebhook(stripeEventPayload('checkout.session.expired', ['status' => 'expired']))->assertOk();

    expect($order->fresh())
        ->payment_status->toBe(OrderPaymentStatusEnum::PAID->value)
        ->order_status->toBe(OrderStatusEnum::Paid->value);
    Notification::assertNothingSent();
});

it('rejects a missing or invalid signature with 400 and changes nothing', function () {
    Notification::fake();
    $order = webhookOrder();
    $payload = stripeEventPayload('checkout.session.completed', ['payment_status' => 'paid']);

    postStripeWebhook($payload, '')->assertBadRequest();
    postStripeWebhook($payload, WebhookSignature::generateSignatureHeader($payload, 'whsec_wrong'))->assertBadRequest();

    expect($order->fresh()->payment_status)->toBe(OrderPaymentStatusEnum::PENDING->value);
    Notification::assertNothingSent();
});

it('rejects a correctly signed body that is not valid JSON with 400', function () {
    postStripeWebhook('not json')->assertBadRequest();
});

it('acknowledges unknown event types and unknown sessions without changing anything', function () {
    Notification::fake();
    $order = webhookOrder();

    postStripeWebhook(stripeEventPayload('checkout.session.async_payment_failed', ['id' => 'cs_test_unknown']))->assertOk();
    postStripeWebhook(json_encode([
        'id' => 'evt_test_123',
        'object' => 'event',
        'type' => 'customer.created',
        'data' => ['object' => ['id' => 'cus_test_123', 'object' => 'customer']],
    ]))->assertOk();

    expect($order->fresh()->payment_status)->toBe(OrderPaymentStatusEnum::PENDING->value);
    Notification::assertNothingSent();
});

it('sends each Notification once when the same event is replayed', function () {
    config(['features.suppliers_enabled' => true]);
    Notification::fake();
    $order = webhookOrder();
    $payload = stripeEventPayload('checkout.session.completed', ['payment_status' => 'paid']);

    postStripeWebhook($payload)->assertOk();
    postStripeWebhook($payload)->assertOk();

    assertNotifiedOf($order->user, NotificationEventTypeEnum::ORDER_PAYMENT_CONFIRMED);
    assertNotifiedOf($order->supplier, NotificationEventTypeEnum::ORDER_READY_FOR_SUPPLIER);
});

it('sends each Notification once when the success redirect arrives after the webhook, without calling Stripe', function () {
    config(['features.suppliers_enabled' => true]);
    Notification::fake();
    mock(StripeService::class)->makePartial()->shouldNotReceive('retrieveSession');
    $order = webhookOrder();

    postStripeWebhook(stripeEventPayload('checkout.session.completed', ['payment_status' => 'paid']))->assertOk();
    $this->actingAs($order->user)->getJson('/api/success?session_id=cs_test_123')
        ->assertOk()
        ->assertJsonPath('payment_status', OrderPaymentStatusEnum::PAID->value);

    assertNotifiedOf($order->user, NotificationEventTypeEnum::ORDER_PAYMENT_CONFIRMED);
    assertNotifiedOf($order->supplier, NotificationEventTypeEnum::ORDER_READY_FOR_SUPPLIER);
});

it('sends each Notification once when the webhook arrives after the success redirect', function () {
    config(['features.suppliers_enabled' => true]);
    Notification::fake();
    mock(StripeService::class)->makePartial()
        ->shouldReceive('retrieveSession')
        ->andReturn(Session::constructFrom(['id' => 'cs_test_123', 'payment_status' => 'paid']));
    $order = webhookOrder();

    $this->actingAs($order->user)->getJson('/api/success?session_id=cs_test_123')->assertOk();
    postStripeWebhook(stripeEventPayload('checkout.session.completed', ['payment_status' => 'paid']))->assertOk();

    assertNotifiedOf($order->user, NotificationEventTypeEnum::ORDER_PAYMENT_CONFIRMED);
    assertNotifiedOf($order->supplier, NotificationEventTypeEnum::ORDER_READY_FOR_SUPPLIER);
});

// CSRF checks are skipped while running tests, so assert the exemption itself.
it('exempts the webhook from CSRF, which statefulApi() applies to requests from the Nuxt origin', function () {
    // The CSRF middleware Sanctum runs for stateful requests.
    expect(app(config('sanctum.middleware.validate_csrf_token'))->getExcludedPaths())->toContain('api/stripe/webhook');
});
