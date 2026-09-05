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

    $response = $this->actingAs($buyer)->getJson('/notifications')->assertOk();

    expect($response->json('data'))->toHaveCount(1);
});

it('lists Notifications for a Supplier and an Admin the same way', function () {
    $supplier = notificationsUser(RolesEnum::ROLE_SUPPLIER);
    $admin = notificationsUser(RolesEnum::ROLE_ADMIN);
    Notification::factory()->create(['notifiable_id' => $supplier->id, 'notifiable_type' => User::class]);
    Notification::factory()->create(['notifiable_id' => $admin->id, 'notifiable_type' => User::class]);

    $this->actingAs($supplier)->getJson('/notifications')->assertOk()->assertJsonCount(1, 'data');
    $this->actingAs($admin)->getJson('/notifications')->assertOk()->assertJsonCount(1, 'data');
});

it("returns the authenticated User's own unread Notification count", function () {
    $buyer = notificationsUser(RolesEnum::ROLE_BUYER);
    $otherBuyer = notificationsUser(RolesEnum::ROLE_BUYER);
    Notification::factory()->count(2)->create(['notifiable_id' => $buyer->id, 'notifiable_type' => User::class]);
    Notification::factory()->create(['notifiable_id' => $buyer->id, 'notifiable_type' => User::class, 'read_at' => now()]);
    Notification::factory()->create(['notifiable_id' => $otherBuyer->id, 'notifiable_type' => User::class]);

    $response = $this->actingAs($buyer)->getJson('/notifications/unread-count')->assertOk();

    expect($response->json('count'))->toBe(2);
});

it('marks Notifications as read when the unread feed is opened, matching auto-mark-as-read-on-open', function () {
    $buyer = notificationsUser(RolesEnum::ROLE_BUYER);
    $notification = Notification::factory()->create([
        'notifiable_id' => $buyer->id,
        'notifiable_type' => User::class,
        'data' => ['event_type' => NotificationEventTypeEnum::ORDER_SHIPPED->value, 'order_id' => 1, 'order_url' => '/orders/1', 'message' => 'Shipped'],
    ]);

    $response = $this->actingAs($buyer)->getJson('/notifications/unread')->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.event_type'))->toBe(NotificationEventTypeEnum::ORDER_SHIPPED->value);
    $this->assertDatabaseMissing('notifications', ['id' => $notification->id, 'read_at' => null]);

    $this->actingAs($buyer)->getJson('/notifications/unread-count')->assertOk()->assertJson(['count' => 0]);
});

it('does not include an already-read Notification in the unread feed', function () {
    $buyer = notificationsUser(RolesEnum::ROLE_BUYER);
    Notification::factory()->read()->create(['notifiable_id' => $buyer->id, 'notifiable_type' => User::class]);

    $this->actingAs($buyer)->getJson('/notifications/unread')->assertOk()->assertJsonCount(0, 'data');
});

it('rejects a guest from listing, counting, or reading Notifications', function () {
    $this->getJson('/notifications')->assertUnauthorized();
    $this->getJson('/notifications/unread')->assertUnauthorized();
    $this->getJson('/notifications/unread-count')->assertUnauthorized();
});
