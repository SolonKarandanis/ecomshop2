<?php

use App\Enums\NotificationEventTypeEnum;
use App\Enums\RolesEnum;
use App\Mail\OrderPlaced;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderNotification;
use App\Services\NotificationHandlerService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

function orderLinksUserWithRole(RolesEnum $role): User
{
    Role::firstOrCreate(['name' => $role->value]);
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

function orderUrlSentTo(User $user, NotificationEventTypeEnum $eventType): ?string
{
    $url = null;
    Notification::assertSentTo(
        $user,
        OrderNotification::class,
        function ($notification, $channels, $notifiable) use ($eventType, &$url) {
            $payload = $notification->toDatabase($notifiable);
            if ($payload['event_type'] !== $eventType->value) {
                return false;
            }
            $url = $payload['order_url'];

            return true;
        }
    );

    return $url;
}

beforeEach(function () {
    config(['app.frontend_url' => 'https://shop.test']);
    Notification::fake();
    Mail::fake();
});

it('links a Buyer\'s Notifications to the frontend order page', function () {
    $order = Order::factory()->create(['user_id' => orderLinksUserWithRole(RolesEnum::ROLE_BUYER)->id]);
    $handler = app(NotificationHandlerService::class);

    $handler->orderCreated($order);
    $handler->orderShipped($order);
    $handler->orderCancelled($order);

    foreach ([
        NotificationEventTypeEnum::ORDER_CREATED,
        NotificationEventTypeEnum::ORDER_SHIPPED,
        NotificationEventTypeEnum::ORDER_CANCELLED,
    ] as $eventType) {
        expect(orderUrlSentTo($order->user, $eventType))->toBe("https://shop.test/orders/{$order->id}");
    }
});

it('links a Supplier\'s ready-for-supplier Notification to the frontend supplier order page', function () {
    $order = Order::factory()->create(['supplier_id' => orderLinksUserWithRole(RolesEnum::ROLE_SUPPLIER)->id]);

    app(NotificationHandlerService::class)->orderReadyForSupplier($order);

    expect(orderUrlSentTo($order->supplier, NotificationEventTypeEnum::ORDER_READY_FOR_SUPPLIER))
        ->toBe("https://shop.test/supplier/orders/{$order->id}");
});

it('links an admin\'s ready-for-supplier Notification to the Filament order page', function () {
    $order = Order::factory()->create(['supplier_id' => orderLinksUserWithRole(RolesEnum::ROLE_ADMIN)->id]);

    app(NotificationHandlerService::class)->orderReadyForSupplier($order);

    expect(orderUrlSentTo($order->supplier, NotificationEventTypeEnum::ORDER_READY_FOR_SUPPLIER))
        ->toBe(route('filament.admin.resources.orders.view', $order->id))
        ->toEndWith("/admin/orders/{$order->id}");
});

it('links an admin\'s own Order to the frontend, since they placed it as the Buyer', function () {
    $order = Order::factory()->create(['user_id' => orderLinksUserWithRole(RolesEnum::ROLE_ADMIN)->id]);

    app(NotificationHandlerService::class)->orderShipped($order);

    expect(orderUrlSentTo($order->user, NotificationEventTypeEnum::ORDER_SHIPPED))
        ->toBe("https://shop.test/orders/{$order->id}");
});

it('links the order-placed email to the frontend order page', function () {
    $order = Order::factory()->create();

    app(NotificationHandlerService::class)->orderCreated($order);

    Mail::assertQueued(OrderPlaced::class, function (OrderPlaced $mail) use ($order) {
        $html = $mail->render();

        return str_contains($html, "https://shop.test/orders/{$order->id}")
            && ! str_contains($html, '/api/');
    });
});
