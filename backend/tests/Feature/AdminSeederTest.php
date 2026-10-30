<?php

use App\Enums\RolesEnum;
use App\Enums\UserStatusEnum;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

it('creates an active, verified admin from the configured email and password', function () {
    config(['app.admin_email' => 'admin@example.com', 'app.admin_password' => 'a-secret-password']);

    $this->seed(DatabaseSeeder::class);

    $admin = User::where('email', 'admin@example.com')->sole();
    expect(Hash::check('a-secret-password', $admin->password))->toBeTrue()
        ->and($admin->status)->toBe(UserStatusEnum::ACTIVE)
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and($admin->hasRole(RolesEnum::ROLE_ADMIN->value))->toBeTrue();
});

it('skips the admin when the email or password is not configured', function (?string $email, ?string $password) {
    config(['app.admin_email' => $email, 'app.admin_password' => $password]);

    $this->seed(DatabaseSeeder::class);

    expect(User::count())->toBe(0);
})->with([
    'neither' => [null, null],
    'no password' => ['admin@example.com', null],
    'no email' => [null, 'a-secret-password'],
    'empty strings' => ['', ''],
]);

it('does not change an existing admin password when the seeder runs again', function () {
    config(['app.admin_email' => 'admin@example.com', 'app.admin_password' => 'first-password']);
    $this->seed(DatabaseSeeder::class);

    config(['app.admin_password' => 'second-password']);
    $this->seed(DatabaseSeeder::class);

    $admin = User::where('email', 'admin@example.com')->sole();
    expect(Hash::check('first-password', $admin->password))->toBeTrue();
});
