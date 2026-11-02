<?php

use App\Enums\NotificationEventTypeEnum;
use App\Enums\RolesEnum;
use App\Models\Notification;
use App\Models\User;
use Spatie\Permission\Models\Role;

function notificationsUser(RolesEnum $role): User
{
    Role::firstOrCreate(['name' => $role->value]);
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

it("lists only the authenticated User's own Notifications, seeded directly via factory", function () {
    $buyer = notificationsUser(RolesEnum::ROLE_BUYER);
    $otherBuyer = notificationsUser(RolesEnum::ROLE_BUYER);
    Notification::factory()->create(['notifiable_id' => $buyer->id, 'notifiable_type' => User::class]);
    Notification::factory()->count(2)->create(['notifiable_id' => $otherBuyer->id, 'notifiable_type' => User::class]);

    $response = $this->actingAs($buyer)->getJson('/api/notifications')->assertOk();

    expect($response->json('data'))->toHaveCount(1);
});

it('lists Notifications for a Supplier and an Admin the same way', function () {
    $supplier = notificationsUser(RolesEnum::ROLE_SUPPLIER);
    $admin = notificationsUser(RolesEnum::ROLE_ADMIN);
    Notification::factory()->create(['notifiable_id' => $supplier->id, 'notifiable_type' => User::class]);
    Notification::factory()->create(['notifiable_id' => $admin->id, 'notifiable_type' => User::class]);

    $this->actingAs($supplier)->getJson('/api/notifications')->assertOk()->assertJsonCount(1, 'data');
    $this->actingAs($admin)->getJson('/api/notifications')->assertOk()->assertJsonCount(1, 'data');
});

it("returns the authenticated User's own unread Notification count", function () {
    $buyer = notificationsUser(RolesEnum::ROLE_BUYER);
    $otherBuyer = notificationsUser(RolesEnum::ROLE_BUYER);
    Notification::factory()->count(2)->create(['notifiable_id' => $buyer->id, 'notifiable_type' => User::class]);
    Notification::factory()->create(['notifiable_id' => $buyer->id, 'notifiable_type' => User::class, 'read_at' => now()]);
    Notification::factory()->create(['notifiable_id' => $otherBuyer->id, 'notifiable_type' => User::class]);

    $response = $this->actingAs($buyer)->getJson('/api/notifications/unread-count')->assertOk();

    expect($response->json('count'))->toBe(2);
});

it('lists unread Notifications without marking them read, so the header bell can show them', function () {
    $buyer = notificationsUser(RolesEnum::ROLE_BUYER);
    $notification = Notification::factory()->create([
        'notifiable_id' => $buyer->id,
        'notifiable_type' => User::class,
        'data' => ['event_type' => NotificationEventTypeEnum::ORDER_SHIPPED->value, 'order_id' => 1, 'order_url' => '/orders/1', 'message' => 'Shipped'],
    ]);

    $response = $this->actingAs($buyer)->getJson('/api/notifications/unread')->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.event_type'))->toBe(NotificationEventTypeEnum::ORDER_SHIPPED->value)
        ->and($notification->fresh()->read_at)->toBeNull();
    $this->actingAs($buyer)->getJson('/api/notifications/unread-count')->assertJson(['count' => 1]);
});

it('marks the given Notifications read and returns the unread count left', function () {
    $buyer = notificationsUser(RolesEnum::ROLE_BUYER);
    [$opened, $alsoOpened, $untouched] = Notification::factory()->count(3)->create(['notifiable_id' => $buyer->id, 'notifiable_type' => User::class]);

    $this->actingAs($buyer)->postJson('/api/notifications/mark-read', ['ids' => [$opened->id, $alsoOpened->id]])
        ->assertOk()
        ->assertExactJson(['count' => 1]);

    expect($opened->fresh()->read_at)->not->toBeNull()
        ->and($alsoOpened->fresh()->read_at)->not->toBeNull()
        ->and($untouched->fresh()->read_at)->toBeNull();
});

it('keeps the first read time when a Notification is marked read again', function () {
    $buyer = notificationsUser(RolesEnum::ROLE_BUYER);
    $notification = Notification::factory()->create(['notifiable_id' => $buyer->id, 'notifiable_type' => User::class, 'read_at' => now()->subDay()]);

    $this->actingAs($buyer)->postJson('/api/notifications/mark-read', ['ids' => [$notification->id]])->assertOk();

    expect($notification->fresh()->read_at->toDateString())->toBe(now()->subDay()->toDateString());
});

it("ignores another User's Notifications when marking read", function () {
    $buyer = notificationsUser(RolesEnum::ROLE_BUYER);
    $otherBuyer = notificationsUser(RolesEnum::ROLE_BUYER);
    $theirs = Notification::factory()->create(['notifiable_id' => $otherBuyer->id, 'notifiable_type' => User::class]);

    $this->actingAs($buyer)->postJson('/api/notifications/mark-read', ['ids' => [$theirs->id]])
        ->assertOk()
        ->assertExactJson(['count' => 0]);
    $this->actingAs($buyer)->postJson('/api/notifications/mark-all-read')->assertOk();

    expect($theirs->fresh()->read_at)->toBeNull();
});

it('rejects marking read without a list of ids', function (mixed $ids) {
    $buyer = notificationsUser(RolesEnum::ROLE_BUYER);

    $this->actingAs($buyer)->postJson('/api/notifications/mark-read', ['ids' => $ids])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('ids');
})->with([
    'missing' => null,
    'empty' => [[]],
    'not a list' => 'abc',
]);

it("marks all of the User's unread Notifications read", function () {
    $buyer = notificationsUser(RolesEnum::ROLE_BUYER);
    Notification::factory()->count(3)->create(['notifiable_id' => $buyer->id, 'notifiable_type' => User::class]);

    $this->actingAs($buyer)->postJson('/api/notifications/mark-all-read')
        ->assertOk()
        ->assertExactJson(['count' => 0]);

    $this->actingAs($buyer)->getJson('/api/notifications/unread')->assertJsonCount(0, 'data');
});

it('does not include an already-read Notification in the unread feed', function () {
    $buyer = notificationsUser(RolesEnum::ROLE_BUYER);
    Notification::factory()->read()->create(['notifiable_id' => $buyer->id, 'notifiable_type' => User::class]);

    $this->actingAs($buyer)->getJson('/api/notifications/unread')->assertOk()->assertJsonCount(0, 'data');
});

it('rejects a guest from listing, counting, or marking Notifications read', function () {
    $this->getJson('/api/notifications')->assertUnauthorized();
    $this->getJson('/api/notifications/unread')->assertUnauthorized();
    $this->getJson('/api/notifications/unread-count')->assertUnauthorized();
    $this->postJson('/api/notifications/mark-read', ['ids' => ['x']])->assertUnauthorized();
    $this->postJson('/api/notifications/mark-all-read')->assertUnauthorized();
});
