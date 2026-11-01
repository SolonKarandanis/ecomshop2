<?php

use App\Enums\NotificationEventTypeEnum;
use App\Enums\PaymentMethodEnum;
use App\Enums\RolesEnum;
use App\Enums\UserStatusEnum;
use App\Mail\OrderPlaced;
use App\Models\Notification as NotificationModel;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\User;
use App\Notifications\OrderNotification;
use App\Services\NotificationHandlerService;
use App\Services\UserService;
use App\Support\FrontendUrl;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Spatie\Permission\Models\Role;
use Symfony\Component\Mailer\SentMessage;

beforeEach(function () {
    config(['app.frontend_url' => 'https://shop.test']);
});

function localeRecipient(RolesEnum $role, string $locale, array $attributes = []): User
{
    Role::firstOrCreate(['name' => $role->value]);
    $user = User::factory()->create(['locale' => $locale, 'status' => UserStatusEnum::ACTIVE->value, ...$attributes]);
    $user->assignRole($role->value);

    return $user;
}

/** @return list<SentMessage> the messages the `array` mailer has sent */
function sentMail(): array
{
    return Mail::mailer()->getSymfonyTransport()->messages()->all();
}

describe('FrontendUrl', function () {
    it('prefixes Greek and Turkish links, and leaves English and unknown locales unprefixed', function (?string $locale, string $url) {
        expect(FrontendUrl::to('/orders/5?tab=items', $locale))->toBe($url);
    })->with([
        'English' => ['en', 'https://shop.test/orders/5?tab=items'],
        'Greek' => ['el', 'https://shop.test/el/orders/5?tab=items'],
        'Turkish' => ['tr', 'https://shop.test/tr/orders/5?tab=items'],
        'no locale' => [null, 'https://shop.test/orders/5?tab=items'],
        'unsupported' => ['fr', 'https://shop.test/orders/5?tab=items'],
    ]);
});

describe('stored Notifications', function () {
    it('stores a translation key, not rendered text, and translates it in the reader\'s language', function () {
        $buyer = localeRecipient(RolesEnum::ROLE_BUYER, 'el');
        $order = Order::factory()->create(['user_id' => $buyer->id]);

        app(NotificationHandlerService::class)->orderShipped($order);

        $stored = NotificationModel::query()->where('notifiable_id', $buyer->id)->sole();
        expect($stored->data)->toMatchArray([
            'message_key' => NotificationEventTypeEnum::ORDER_SHIPPED->value,
            'message_params' => ['order' => $order->id],
        ])->not->toHaveKey('message');

        $this->actingAs($buyer);
        $this->getJson('/api/notifications', ['Accept-Language' => 'tr'])
            ->assertJsonPath('data.0.message', "#{$order->id} numaralı siparişiniz kargoya verildi.");
        $this->getJson('/api/notifications', ['Accept-Language' => 'el'])
            ->assertJsonPath('data.0.message', "Η παραγγελία σας #{$order->id} απεστάλη.");
        $this->getJson('/api/notifications/unread')
            ->assertJsonPath('data.0.message', "Your order #{$order->id} has been shipped.");
    });

    it('shows a Notification stored before the change with its original message', function () {
        $buyer = localeRecipient(RolesEnum::ROLE_BUYER, 'el');
        NotificationModel::factory()->create([
            'notifiable_id' => $buyer->id,
            'notifiable_type' => User::class,
            'data' => ['event_type' => NotificationEventTypeEnum::ORDER_SHIPPED->value, 'order_id' => 1, 'message' => 'Your order #1 has been shipped.'],
        ]);

        $this->actingAs($buyer)
            ->getJson('/api/notifications', ['Accept-Language' => 'tr'])
            ->assertJsonPath('data.0.message', 'Your order #1 has been shipped.');
    });

    it('translates a Supplier awaiting approval with its name', function () {
        $admin = localeRecipient(RolesEnum::ROLE_ADMIN, 'en');
        $supplier = localeRecipient(RolesEnum::ROLE_SUPPLIER, 'en', ['name' => 'Acme']);

        app(NotificationHandlerService::class)->supplierAwaitingApproval($supplier, collect([$admin]));

        $this->actingAs($admin)
            ->getJson('/api/notifications', ['Accept-Language' => 'el'])
            ->assertJsonPath('data.0.message', 'Ο προμηθευτής Acme εγγράφηκε και περιμένει έγκριση.');
    });

    it("links each recipient's order Notifications in their own language", function () {
        Notification::fake();
        $order = Order::factory()->create([
            'user_id' => localeRecipient(RolesEnum::ROLE_BUYER, 'el')->id,
            'supplier_id' => localeRecipient(RolesEnum::ROLE_SUPPLIER, 'tr')->id,
        ]);
        $handler = app(NotificationHandlerService::class);

        $handler->orderShipped($order);
        $handler->orderReadyForSupplier($order);

        Notification::assertSentTo($order->user, OrderNotification::class, fn ($notification, $channels, $notifiable) => $notification->toDatabase($notifiable)['order_url'] === "https://shop.test/el/orders/{$order->id}");
        Notification::assertSentTo($order->supplier, OrderNotification::class, fn ($notification, $channels, $notifiable) => $notification->toDatabase($notifiable)['order_url'] === "https://shop.test/tr/supplier/orders/{$order->id}");
    });
});

describe('emails', function () {
    it("sends the queued order-placed email in the Buyer's language, linking to their language's order page", function () {
        $buyer = localeRecipient(RolesEnum::ROLE_BUYER, 'el');
        $order = Order::factory()->create(['user_id' => $buyer->id, 'grand_total' => '24.99', 'shipping_amount' => '5.00']);
        OrderItem::create(['order_id' => $order->id, 'product_id' => Product::factory()->create()->id, 'quantity' => 1, 'unit_amount' => '19.99', 'total_amount' => '19.99']);

        // Through the (sync) queue and the `array` mailer, as the cron's worker would send it.
        app(NotificationHandlerService::class)->orderCreated($order);

        $mail = collect(sentMail())->first(fn (SentMessage $sent) => str_contains($sent->toString(), 'EcomShop'));
        $email = $mail->getOriginalMessage();
        expect($email->getSubject())->toBe('Η παραγγελία υποβλήθηκε - EcomShop')
            ->and($email->getHtmlBody())
            ->toContain('Επιβεβαίωση παραγγελίας')
            ->toContain("https://shop.test/el/orders/{$order->id}")
            ->toContain("24,99\u{a0}€");
    });

    it('queues the order-placed email with the Buyer\'s locale', function () {
        Mail::fake();
        $order = Order::factory()->create(['user_id' => localeRecipient(RolesEnum::ROLE_BUYER, 'tr')->id]);

        app(NotificationHandlerService::class)->orderCreated($order);

        Mail::assertQueued(OrderPlaced::class, fn (OrderPlaced $mail) => $mail->locale === 'tr');
    });

    it("sends the Supplier-approved email in the Supplier's language, linking to their language's login page", function () {
        $supplier = localeRecipient(RolesEnum::ROLE_SUPPLIER, 'tr', ['status' => UserStatusEnum::PENDING->value]);

        app(UserService::class)->activateUser($supplier);

        $email = sentMail()[0]->getOriginalMessage();
        expect($email->getSubject())->toBe('Tedarikçi hesabınız onaylandı - EcomShop')
            ->and($email->getHtmlBody())
            ->toContain('Bir yönetici tedarikçi hesabınızı onayladı.')
            ->toContain('https://shop.test/tr/login');
    });

    it("links the password-reset email to the reset page in the user's language", function (string $locale, string $prefix) {
        Notification::fake();
        $user = localeRecipient(RolesEnum::ROLE_BUYER, $locale);

        Password::sendResetLink(['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user, $prefix) {
            $url = $notification->toMail($user)->actionUrl;

            return str_starts_with($url, "https://shop.test{$prefix}/reset-password?token=")
                && str_contains($url, 'email='.urlencode($user->email));
        });
    })->with([
        'Turkish' => ['tr', '/tr'],
        'Greek' => ['el', '/el'],
        'English' => ['en', ''],
    ]);
});

it("sends a Cash on Delivery Buyer to the success page in the language they're checking out in", function () {
    Notification::fake();
    Mail::fake();
    PaymentMethod::firstOrCreate(['resource_key' => PaymentMethodEnum::CASH_ON_DELIVERY->value]);
    $buyer = localeRecipient(RolesEnum::ROLE_BUYER, 'en');
    $this->actingAs($buyer)->postJson('/api/cart/items', ['product_id' => Product::factory()->create(['price' => 10])->id, 'quantity' => 1])->assertOk();

    $response = $this->postJson('/api/checkout', [
        'firstName' => 'Jane', 'lastName' => 'Doe', 'phone' => '555', 'address' => '1 Main St',
        'city' => 'Athens', 'country' => 'Greece', 'zipCode' => '12345',
        'paymentMethod' => PaymentMethodEnum::CASH_ON_DELIVERY->value,
    ], ['Accept-Language' => 'tr'])->assertOk();

    $order = Order::where('user_id', $buyer->id)->sole();
    expect($response->json('redirect_url'))->toBe("https://shop.test/tr/success?order_id={$order->id}");
});
