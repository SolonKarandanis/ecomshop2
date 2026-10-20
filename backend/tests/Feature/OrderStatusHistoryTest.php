<?php

use App\Enums\OrderPaymentStatusEnum;
use App\Enums\OrderStatusEnum;
use App\Enums\RolesEnum;
use App\Enums\UserStatusEnum;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\StripeOrderDetail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Stripe\WebhookSignature;

beforeEach(function () {
    Notification::fake();
});

function historyUser(RolesEnum $role): User
{
    Role::firstOrCreate(['name' => $role->value]);
    $user = User::factory()->create(['status' => UserStatusEnum::ACTIVE->value]);
    $user->assignRole($role->value);

    return $user;
}

/**
 * @return list<array{order_status: string, payment_status: string|null, changed_by: int|null}>
 */
function historyOf(Order $order): array
{
    return $order->statusHistory()->get()
        ->map(fn (OrderStatusHistory $entry) => $entry->only(['order_status', 'payment_status', 'changed_by']))
        ->all();
}

function draftPending(?int $changedBy = null): array
{
    return [
        'order_status' => OrderStatusEnum::Draft->value,
        'payment_status' => OrderPaymentStatusEnum::PENDING->value,
        'changed_by' => $changedBy,
    ];
}

it('gives a new Order one entry with its initial statuses', function () {
    $order = Order::factory()->create();

    expect(historyOf($order))->toBe([draftPending()]);
});

it('records the acting user when an Order is created during a request', function () {
    $buyer = historyUser(RolesEnum::ROLE_BUYER);
    $this->actingAs($buyer);

    $order = Order::factory()->create(['user_id' => $buyer->id]);

    expect(historyOf($order))->toBe([draftPending($buyer->id)]);
});

it('adds one entry, by the Supplier, when a Supplier ships an Order', function () {
    config(['features.suppliers_enabled' => true]);
    $supplier = historyUser(RolesEnum::ROLE_SUPPLIER);
    $order = Order::factory()->create(['supplier_id' => $supplier->id, 'order_status' => OrderStatusEnum::Paid->value]);

    $this->actingAs($supplier)->postJson("/api/supplier-orders/{$order->id}/ship")->assertOk();

    expect(historyOf($order))->toHaveCount(2)
        ->and(historyOf($order)[1])->toBe([
            'order_status' => OrderStatusEnum::Shipped->value,
            'payment_status' => OrderPaymentStatusEnum::PENDING->value,
            'changed_by' => $supplier->id,
        ]);
});

it('adds one entry, with no user, when the Stripe webhook marks an Order Paid', function () {
    config(['app.stripe_webhook_secret' => 'whsec_history']);
    $order = Order::factory()->create();
    StripeOrderDetail::create(['order_id' => $order->id, 'session_id' => 'cs_history']);
    $payload = json_encode([
        'id' => 'evt_history',
        'object' => 'event',
        'type' => 'checkout.session.completed',
        'data' => ['object' => ['id' => 'cs_history', 'object' => 'checkout.session', 'payment_status' => 'paid']],
    ]);

    $this->call('POST', '/api/stripe/webhook', server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => WebhookSignature::generateSignatureHeader($payload, 'whsec_history'),
    ], content: $payload)->assertOk();

    // Order status and payment status change in one save: one entry, not two.
    expect(historyOf($order))->toBe([draftPending(), [
        'order_status' => OrderStatusEnum::Paid->value,
        'payment_status' => OrderPaymentStatusEnum::PAID->value,
        'changed_by' => null,
    ]]);
});

it('adds one entry, by the admin, for a status change from the Filament edit form or the Orders list', function () {
    $admin = historyUser(RolesEnum::ROLE_ADMIN);
    $this->actingAs($admin);
    $order = Order::factory()->create();

    Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
        ->fillForm(['order_status' => OrderStatusEnum::Paid->value])
        ->call('save')
        ->assertHasNoFormErrors();

    Livewire::test(ListOrders::class)
        ->call('updateTableColumnState', 'order_status', (string) $order->getKey(), OrderStatusEnum::Shipped->value);

    expect(collect(historyOf($order))->pluck('order_status')->all())->toBe([
        OrderStatusEnum::Draft->value,
        OrderStatusEnum::Paid->value,
        OrderStatusEnum::Shipped->value,
    ]);
    expect(collect(historyOf($order))->skip(1)->pluck('changed_by')->unique()->all())->toBe([$admin->id]);
});

it('adds no entry when an Order is saved without a status change', function () {
    $order = Order::factory()->create();

    $order->update(['notes' => 'Leave at the door']);

    expect(historyOf($order))->toHaveCount(1);
});

it('writes no entry for a status change that is rolled back', function () {
    $order = Order::factory()->create();

    try {
        DB::transaction(function () use ($order) {
            $order->update(['order_status' => OrderStatusEnum::Cancelled->value]);
            throw new RuntimeException('rolled back');
        });
    } catch (RuntimeException) {
    }

    expect(historyOf($order))->toHaveCount(1);
});

it("returns the Order's history oldest first, without who made each change, on the detail endpoint only", function () {
    $buyer = historyUser(RolesEnum::ROLE_BUYER);
    $order = Order::factory()->create(['user_id' => $buyer->id]);
    $order->update(['order_status' => OrderStatusEnum::Paid->value, 'payment_status' => OrderPaymentStatusEnum::PAID->value]);
    $order->update(['order_status' => OrderStatusEnum::Shipped->value]);

    $detail = $this->actingAs($buyer)->getJson("/api/orders/{$order->id}")->assertOk();

    expect(collect($detail->json('data.status_history'))->pluck('order_status')->all())->toBe([
        OrderStatusEnum::Draft->value,
        OrderStatusEnum::Paid->value,
        OrderStatusEnum::Shipped->value,
    ]);
    expect(array_keys($detail->json('data.status_history.0')))->toBe(['order_status', 'payment_status', 'created_at']);

    $list = $this->actingAs($buyer)->getJson('/api/orders')->assertOk();
    expect($list->json('data.0'))->not->toHaveKey('status_history');
});

it('backfills one entry with its current statuses for each Order that has none', function () {
    $orderWithout = Order::factory()->create(['order_status' => OrderStatusEnum::Delivered->value, 'payment_status' => OrderPaymentStatusEnum::PAID->value]);
    $orderWithout->statusHistory()->delete();
    $orderWith = Order::factory()->create();

    (require database_path('migrations/2026_10_20_041500_backfill_order_status_histories.php'))->up();

    expect(historyOf($orderWithout))->toBe([[
        'order_status' => OrderStatusEnum::Delivered->value,
        'payment_status' => OrderPaymentStatusEnum::PAID->value,
        'changed_by' => null,
    ]]);
    expect($orderWithout->statusHistory()->value('created_at'))->toEqual($orderWithout->created_at);
    expect(historyOf($orderWith))->toHaveCount(1);
});

it('shows admins the full history, including who made each change, in Filament', function () {
    $admin = historyUser(RolesEnum::ROLE_ADMIN);
    $supplier = historyUser(RolesEnum::ROLE_SUPPLIER);
    $order = Order::factory()->create();
    $this->actingAs($supplier);
    $order->update(['order_status' => OrderStatusEnum::Paid->value]);

    $this->actingAs($admin)->get("/admin/orders/{$order->id}")
        ->assertOk()
        ->assertSee('Status history')
        ->assertSee($supplier->name)
        ->assertSee('System');
});
