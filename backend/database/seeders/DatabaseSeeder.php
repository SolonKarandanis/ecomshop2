<?php

namespace Database\Seeders;

use App\Enums\RolesEnum;
use App\Enums\UserStatusEnum;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(PaymentMethodSeeder::class);
        $this->call(RoleSeeder::class);
        $this->seedAdmin();
    }

    /**
     * Create the Filament admin from ADMIN_EMAIL / ADMIN_PASSWORD. The password only
     * applies when the account is created, so re-seeding on every deploy (ADR-0001)
     * never resets a password changed since. Uses User::create() rather than the
     * factory, because Faker isn't installed in production (composer --no-dev).
     */
    private function seedAdmin(): void
    {
        $email = config('app.admin_email');
        $password = config('app.admin_password');

        if (blank($email) || blank($password)) {
            $this->command?->warn('ADMIN_EMAIL or ADMIN_PASSWORD is not set; skipping the admin user.');

            return;
        }

        $admin = User::query()->firstOrCreate(['email' => $email], [
            'name' => 'Admin',
            'password' => $password,
            'status' => UserStatusEnum::ACTIVE->value,
            'email_verified_at' => now(),
        ]);

        if (! $admin->hasRole(RolesEnum::ROLE_ADMIN->value)) {
            $admin->assignRole(RolesEnum::ROLE_ADMIN->value);
        }
    }
}
