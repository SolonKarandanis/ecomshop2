<?php

use App\Enums\OrderStatusEnum;
use App\Exceptions\OrderException;
use App\Exports\OrdersExport;
use App\Listeners\TransferGuestCartToUser;
use App\Mail\OrderPlaced;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Notifications\OrderNotification;
use App\Observers\OrderObserver;
use App\Repositories\OrderRepository;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

it('migrates the expected schema', function () {
    $tables = [
        'users', 'products', 'categories', 'carts', 'cart_items',
        'orders', 'order_items', 'addresses', 'reviews', 'notifications',
        'payment_methods', 'stripe_order_details', 'roles', 'permissions',
    ];

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue("Expected table [$table] to exist");
    }
});

it('seeds idempotently', function () {
    Artisan::call('db:seed');
    $roles = Role::count();
    $paymentMethods = PaymentMethod::count();
    $users = User::count();

    Artisan::call('db:seed');

    expect(Role::count())->toBe($roles)
        ->and(PaymentMethod::count())->toBe($paymentMethods)
        ->and(User::count())->toBe($users);
});

it('ported classes are reachable with no Livewire/Blade/HTTP coupling', function () {
    expect(class_exists(OrderRepository::class))->toBeTrue()
        ->and(class_exists(OrderException::class))->toBeTrue()
        ->and(class_exists(OrderObserver::class))->toBeTrue()
        ->and(class_exists(TransferGuestCartToUser::class))->toBeTrue()
        ->and(class_exists(OrderPlaced::class))->toBeTrue()
        ->and(class_exists(OrdersExport::class))->toBeTrue()
        ->and(class_exists(OrderStatusEnum::class))->toBeTrue();

    app(OrderRepository::class);
});

it('fires a notification when the observed order status changes, with no HTTP layer involved', function () {
    Notification::fake();

    $buyer = User::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $buyer->id,
        'order_status' => OrderStatusEnum::Draft->value,
    ]);

    $order->update(['order_status' => OrderStatusEnum::Shipped->value]);
    $order->update(['order_status' => OrderStatusEnum::Delivered->value]);

    $cancelledOrder = Order::factory()->create([
        'user_id' => $buyer->id,
        'order_status' => OrderStatusEnum::Draft->value,
    ]);
    $cancelledOrder->update(['order_status' => OrderStatusEnum::Cancelled->value]);

    Notification::assertSentTo($buyer, OrderNotification::class, 3);
});

it('has no web routes or Livewire surface in the backend', function () {
    expect(file_exists(base_path('routes/web.php')))->toBeFalse()
        ->and(is_dir(app_path('Livewire')))->toBeFalse();
});
