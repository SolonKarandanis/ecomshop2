<?php

use App\Enums\NotificationEventTypeEnum;
use App\Enums\RolesEnum;
use App\Enums\UserStatusEnum;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\User;
use App\Notifications\SupplierApprovedNotification;
use App\Notifications\SupplierAwaitingApprovalNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function supplierRegistrationUser(RolesEnum $role, UserStatusEnum $status = UserStatusEnum::ACTIVE): User
{
    $user = User::factory()->create(['status' => $status->value, 'password' => 'password123']);
    $user->assignRole($role->value);

    return $user;
}

function registerSupplier(): TestResponse
{
    return test()->withHeader('Origin', 'http://localhost:3000')->postJson('/api/register', [
        'name' => 'Sam Supplier',
        'email' => 'sam@example.com',
        'password' => 'password123',
        'role' => RolesEnum::ROLE_SUPPLIER->value,
    ]);
}

beforeEach(function () {
    foreach (RolesEnum::cases() as $role) {
        Role::firstOrCreate(['name' => $role->value]);
    }
    config(['features.suppliers_enabled' => true, 'app.frontend_url' => 'https://shop.test']);
});

it('serves the public config without authentication', function (bool $enabled) {
    config(['features.suppliers_enabled' => $enabled]);

    $this->getJson('/api/config')
        ->assertOk()
        ->assertExactJson(['data' => ['suppliers_enabled' => $enabled]]);
})->with([true, false]);

it('creates a registering Supplier as PENDING and does not log them in', function () {
    Notification::fake();

    registerSupplier()
        ->assertCreated()
        ->assertJsonPath('data.status', UserStatusEnum::PENDING->value)
        ->assertJsonPath('data.roles', [RolesEnum::ROLE_SUPPLIER->value]);

    $supplier = User::where('email', 'sam@example.com')->firstOrFail();
    expect($supplier->status)->toBe(UserStatusEnum::PENDING)
        ->and($supplier->hasRole(RolesEnum::ROLE_SUPPLIER->value))->toBeTrue();

    $this->assertGuest('web');
    $this->app['auth']->forgetGuards();
    $this->getJson('/api/user')->assertUnauthorized();
});

it('notifies every admin, with a link to the Filament user page, when a Supplier registers', function () {
    Notification::fake();
    $admins = collect([
        supplierRegistrationUser(RolesEnum::ROLE_ADMIN),
        supplierRegistrationUser(RolesEnum::ROLE_ADMIN),
    ]);
    $buyer = supplierRegistrationUser(RolesEnum::ROLE_BUYER);

    registerSupplier()->assertCreated();
    $supplier = User::where('email', 'sam@example.com')->firstOrFail();

    Notification::assertSentTo(
        $admins,
        SupplierAwaitingApprovalNotification::class,
        fn ($notification, $channels, $notifiable) => $notification->toDatabase($notifiable)['url']
            === route('filament.admin.resources.users.view', $supplier->id)
    );
    Notification::assertNotSentTo($buyer, SupplierAwaitingApprovalNotification::class);
});

it('lists the awaiting-approval Notification for an admin with its url', function () {
    $admin = supplierRegistrationUser(RolesEnum::ROLE_ADMIN);

    registerSupplier()->assertCreated();
    $supplier = User::where('email', 'sam@example.com')->firstOrFail();

    $this->actingAs($admin)->getJson('/api/notifications')
        ->assertOk()
        ->assertJsonPath('data.0.event_type', NotificationEventTypeEnum::SUPPLIER_AWAITING_APPROVAL->value)
        ->assertJsonPath('data.0.url', route('filament.admin.resources.users.view', $supplier->id))
        ->assertJsonPath('data.0.order_id', null);
});

it('refuses to log in a PENDING Supplier, without a session or a rate-limiter hit', function () {
    $supplier = supplierRegistrationUser(RolesEnum::ROLE_SUPPLIER, UserStatusEnum::PENDING);

    $this->postJson('/api/login', ['email' => $supplier->email, 'password' => 'password123'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email' => __('user.awaiting_approval')]);

    $this->assertGuest('web');
    expect(RateLimiter::attempts(strtolower($supplier->email).'|127.0.0.1'))->toBe(0);
});

it('lets an admin find pending Suppliers with the Filament filter', function () {
    $this->actingAs(supplierRegistrationUser(RolesEnum::ROLE_ADMIN));
    $pending = supplierRegistrationUser(RolesEnum::ROLE_SUPPLIER, UserStatusEnum::PENDING);
    $active = supplierRegistrationUser(RolesEnum::ROLE_SUPPLIER);
    $inactive = supplierRegistrationUser(RolesEnum::ROLE_SUPPLIER, UserStatusEnum::INACTIVE);

    Livewire::test(ListUsers::class)
        ->filterTable('suppliers_awaiting_approval')
        ->assertCanSeeTableRecords([$pending])
        ->assertCanNotSeeTableRecords([$active, $inactive]);
});

it('lets an admin approve a pending Supplier, who is notified by email and can then log in', function () {
    Notification::fake();
    $this->actingAs(supplierRegistrationUser(RolesEnum::ROLE_ADMIN));
    $supplier = supplierRegistrationUser(RolesEnum::ROLE_SUPPLIER, UserStatusEnum::PENDING);

    Livewire::test(ListUsers::class)
        ->assertTableActionVisible('approve', $supplier)
        ->callTableAction('approve', $supplier);

    expect($supplier->refresh()->status)->toBe(UserStatusEnum::ACTIVE);
    Notification::assertSentTo(
        $supplier,
        SupplierApprovedNotification::class,
        fn ($notification, $channels) => $channels === ['database', 'mail']
    );

    auth()->guard('web')->logout();
    $client = $this->withHeader('Origin', 'http://localhost:3000');
    $client->postJson('/api/login', ['email' => $supplier->email, 'password' => 'password123'])->assertOk();
    $client->getJson('/api/supplier-orders')->assertOk();
});

it('offers Approve on the Filament user page of a pending Supplier only', function () {
    $this->actingAs(supplierRegistrationUser(RolesEnum::ROLE_ADMIN));
    $pending = supplierRegistrationUser(RolesEnum::ROLE_SUPPLIER, UserStatusEnum::PENDING);
    $active = supplierRegistrationUser(RolesEnum::ROLE_SUPPLIER);

    Livewire::test(ViewUser::class, ['record' => $pending->getRouteKey()])->assertActionVisible('approve');
    Livewire::test(ViewUser::class, ['record' => $active->getRouteKey()])->assertActionHidden('approve');
});

it('does not send the approval Notification when re-activating a deactivated user', function () {
    Notification::fake();
    $this->actingAs(supplierRegistrationUser(RolesEnum::ROLE_ADMIN));
    $supplier = supplierRegistrationUser(RolesEnum::ROLE_SUPPLIER, UserStatusEnum::INACTIVE);

    Livewire::test(ListUsers::class)->callTableAction('activate', $supplier);

    expect($supplier->refresh()->status)->toBe(UserStatusEnum::ACTIVE);
    Notification::assertNotSentTo($supplier, SupplierApprovedNotification::class);
});

it('forbids the Supplier endpoints to a Supplier who is not ACTIVE', function (UserStatusEnum $status) {
    $supplier = supplierRegistrationUser(RolesEnum::ROLE_SUPPLIER, $status);

    $this->actingAs($supplier)->getJson('/api/supplier-orders')->assertForbidden();
})->with([UserStatusEnum::INACTIVE, UserStatusEnum::PENDING]);
