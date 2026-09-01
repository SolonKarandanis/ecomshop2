<?php

use App\Enums\RolesEnum;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Spatie\Permission\Models\Role;

function seedBuyerAndSupplierRoles(): void
{
    Role::firstOrCreate(['name' => RolesEnum::ROLE_BUYER->value]);
    Role::firstOrCreate(['name' => RolesEnum::ROLE_SUPPLIER->value]);
}

it('registers a new user as a Buyer by default and authenticates the session', function () {
    seedBuyerAndSupplierRoles();

    $client = $this->withHeader('Origin', 'http://localhost:3000');

    $response = $client->postJson('/register', [
        'name' => 'Jane Buyer',
        'email' => 'jane@example.com',
        'password' => 'password123',
    ])->assertCreated();

    $response->assertJsonPath('data.email', 'jane@example.com');

    $user = User::where('email', 'jane@example.com')->firstOrFail();
    expect($user->hasRole(RolesEnum::ROLE_BUYER->value))->toBeTrue();

    // A real request re-resolves the user from the database (wasRecentlyCreated
    // would be false); the in-process test container reuses the just-inserted
    // model on the cached guard, so drop it to simulate that fresh resolution.
    $this->app['auth']->forgetGuards();

    $client->getJson('/user')->assertOk()->assertJsonPath('data.id', $user->id);
});

it('registers a new user as a Supplier when the Suppliers Feature is enabled', function () {
    seedBuyerAndSupplierRoles();
    config(['features.suppliers_enabled' => true]);

    $this->withHeader('Origin', 'http://localhost:3000')->postJson('/register', [
        'name' => 'Sam Supplier',
        'email' => 'sam@example.com',
        'password' => 'password123',
        'role' => RolesEnum::ROLE_SUPPLIER->value,
    ])->assertCreated();

    $user = User::where('email', 'sam@example.com')->firstOrFail();
    expect($user->hasRole(RolesEnum::ROLE_SUPPLIER->value))->toBeTrue();
});

it('rejects registering as a Supplier while the Suppliers Feature is off', function () {
    seedBuyerAndSupplierRoles();
    config(['features.suppliers_enabled' => false]);

    $this->postJson('/register', [
        'name' => 'Sam Supplier',
        'email' => 'sam@example.com',
        'password' => 'password123',
        'role' => RolesEnum::ROLE_SUPPLIER->value,
    ])->assertUnprocessable()->assertJsonValidationErrors('role');

    expect(User::where('email', 'sam@example.com')->exists())->toBeFalse();
});

it('rejects registration with an already-used email', function () {
    seedBuyerAndSupplierRoles();
    $existing = User::factory()->create();

    $this->postJson('/register', [
        'name' => 'Duplicate',
        'email' => $existing->email,
        'password' => 'password123',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('sends a password reset link for a known email', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->postJson('/forgot-password', ['email' => $user->email])
        ->assertOk()
        ->assertJsonPath('message', trans(Password::RESET_LINK_SENT));

    Notification::assertSentTo($user, ResetPassword::class);
});

it('rejects a password reset link request for an unknown email', function () {
    $this->postJson('/forgot-password', ['email' => 'nobody@example.com'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('resets the password given a valid token', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $this->postJson('/reset-password', [
        'email' => $user->email,
        'token' => $token,
        'password' => 'new-password123',
        'password_confirmation' => 'new-password123',
    ])->assertOk()->assertJsonPath('message', trans(Password::PASSWORD_RESET));

    $this->withHeader('Origin', 'http://localhost:3000')->postJson('/login', [
        'email' => $user->email,
        'password' => 'new-password123',
    ])->assertOk();
});

it('rejects resetting the password with an invalid token', function () {
    $user = User::factory()->create();

    $this->postJson('/reset-password', [
        'email' => $user->email,
        'token' => 'not-a-real-token',
        'password' => 'new-password123',
        'password_confirmation' => 'new-password123',
    ])->assertUnprocessable()->assertJsonPath('message', trans(Password::INVALID_TOKEN));
});
