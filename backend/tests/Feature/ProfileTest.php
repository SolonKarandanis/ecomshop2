<?php

use App\Enums\RolesEnum;
use App\Enums\UserStatusEnum;
use App\Models\Address;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

function profileBuyer(): User
{
    Role::firstOrCreate(['name' => RolesEnum::ROLE_BUYER->value]);
    $buyer = User::factory()->create(['password' => Hash::make('old-password')]);
    $buyer->assignRole(RolesEnum::ROLE_BUYER->value);

    return $buyer;
}

function profileAdmin(): User
{
    Role::firstOrCreate(['name' => RolesEnum::ROLE_ADMIN->value]);
    $admin = User::factory()->create();
    $admin->assignRole(RolesEnum::ROLE_ADMIN->value);

    return $admin;
}

it("returns the authenticated Buyer's own name and email, without a User Status", function () {
    $buyer = profileBuyer();

    $response = $this->actingAs($buyer)->getJson('/profile')->assertOk();

    expect($response->json('data.name'))->toBe($buyer->name);
    expect($response->json('data.email'))->toBe($buyer->email);
    expect($response->json('data'))->not->toHaveKey('status');
});

it("returns the authenticated Admin's own User Status alongside name and email", function () {
    $admin = profileAdmin();

    $response = $this->actingAs($admin)->getJson('/profile')->assertOk();

    expect($response->json('data.status'))->toBe(UserStatusEnum::ACTIVE->value);
});

it("updates the authenticated User's name and email", function () {
    $buyer = profileBuyer();

    $response = $this->actingAs($buyer)
        ->patchJson('/profile', ['name' => 'New Name', 'email' => 'new@example.com'])
        ->assertOk();

    expect($response->json('data.name'))->toBe('New Name');
    expect($response->json('data.email'))->toBe('new@example.com');
    $this->assertDatabaseHas('users', ['id' => $buyer->id, 'name' => 'New Name', 'email' => 'new@example.com']);
});

it('rejects updating the profile to an email already used by another User', function () {
    $buyer = profileBuyer();
    $otherBuyer = profileBuyer();

    $this->actingAs($buyer)
        ->patchJson('/profile', ['name' => $buyer->name, 'email' => $otherBuyer->email])
        ->assertStatus(400);
});

it('changes the password given the correct current password', function () {
    $buyer = profileBuyer();

    $this->actingAs($buyer)
        ->patchJson('/profile/password', [
            'currentPassword' => 'old-password',
            'newPassword' => 'new-password',
            'newPasswordConfirmation' => 'new-password',
        ])
        ->assertNoContent();

    expect(Hash::check('new-password', $buyer->fresh()->password))->toBeTrue();
});

it('rejects changing the password given the wrong current password', function () {
    $buyer = profileBuyer();

    $this->actingAs($buyer)
        ->patchJson('/profile/password', [
            'currentPassword' => 'wrong-password',
            'newPassword' => 'new-password',
            'newPasswordConfirmation' => 'new-password',
        ])
        ->assertStatus(400);

    expect(Hash::check('old-password', $buyer->fresh()->password))->toBeTrue();
});

it('rejects a new password that does not match its confirmation', function () {
    $buyer = profileBuyer();

    $this->actingAs($buyer)
        ->patchJson('/profile/password', [
            'currentPassword' => 'old-password',
            'newPassword' => 'new-password',
            'newPasswordConfirmation' => 'does-not-match',
        ])
        ->assertUnprocessable();
});

it("lists the authenticated User's own past Order Addresses, read-only", function () {
    $buyer = profileBuyer();
    $otherBuyer = profileBuyer();
    $order = Order::factory()->create(['user_id' => $buyer->id]);
    Address::create([
        'user_id' => $buyer->id,
        'order_id' => $order->id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'phone' => '555-1234',
        'street_address' => '1 Main St',
        'city' => 'Athens',
        'country' => 'Greece',
        'postal_code' => '12345',
    ]);
    $otherOrder = Order::factory()->create(['user_id' => $otherBuyer->id]);
    Address::create([
        'user_id' => $otherBuyer->id,
        'order_id' => $otherOrder->id,
        'first_name' => 'John',
        'last_name' => 'Roe',
        'phone' => '555-5678',
        'street_address' => '2 Main St',
        'city' => 'Sparta',
        'country' => 'Greece',
        'postal_code' => '54321',
    ]);

    $response = $this->actingAs($buyer)->getJson('/profile/addresses')->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.city'))->toBe('Athens');
});

it('rejects a guest from viewing or updating the profile, changing the password, or listing addresses', function () {
    $this->getJson('/profile')->assertUnauthorized();
    $this->patchJson('/profile', ['name' => 'x', 'email' => 'x@example.com'])->assertUnauthorized();
    $this->patchJson('/profile/password', ['currentPassword' => 'a', 'newPassword' => 'newpassword', 'newPasswordConfirmation' => 'newpassword'])->assertUnauthorized();
    $this->getJson('/profile/addresses')->assertUnauthorized();
});
