<?php

namespace Database\Seeders;

use App\Enums\RolesEnum;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

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
        $adminRole = Role::query()->where('name', RolesEnum::ROLE_ADMIN->value)->first();
        $adminUser = User::query()->where('email','skarandanis@gmail.com')->first();

        if(is_null($adminUser)){
            $adminUser = User::factory()->create([
                'name' => 'Admin',
                'email' => 'skarandanis@gmail.com',
                'password' => bcrypt('7ujm&UJM'),
            ]);
        }

        if ($adminRole && !$adminUser->hasRole($adminRole)) {
            $adminUser->assignRole($adminRole);
        }

    }
}
