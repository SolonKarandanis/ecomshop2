<?php

use App\Enums\RolesEnum;
use App\Enums\UserStatusEnum;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

function gatesUser(RolesEnum $role, UserStatusEnum $status = UserStatusEnum::ACTIVE): User
{
    Role::firstOrCreate(['name' => $role->value]);
    $user = User::factory()->create(['status' => $status->value]);
    $user->assignRole($role->value);

    return $user;
}

beforeEach(function () {
    config(['features.suppliers_enabled' => true]);
});

// Each gate's who-gets-in, guests included. A guest passing admin-action or
// supplier-action would only take one of them on a public route.
it('lets only the right users through each gate', function (string $gate, ?Closure $user, bool $allowed) {
    $gates = $user ? Gate::forUser($user()) : Gate::forUser(null);

    expect($gates->allows($gate))->toBe($allowed);
})->with([
    'a guest, buyer-action (guest cart)' => ['buyer-action', null, true],
    'a Buyer, buyer-action' => ['buyer-action', fn () => gatesUser(RolesEnum::ROLE_BUYER), true],
    'an Admin, buyer-action' => ['buyer-action', fn () => gatesUser(RolesEnum::ROLE_ADMIN), false],
    'a guest, supplier-action' => ['supplier-action', null, false],
    'an active Supplier, supplier-action' => ['supplier-action', fn () => gatesUser(RolesEnum::ROLE_SUPPLIER), true],
    'a pending Supplier, supplier-action' => ['supplier-action', fn () => gatesUser(RolesEnum::ROLE_SUPPLIER, UserStatusEnum::PENDING), false],
    'a Buyer, supplier-action' => ['supplier-action', fn () => gatesUser(RolesEnum::ROLE_BUYER), false],
    'a guest, admin-action' => ['admin-action', null, false],
    'an Admin, admin-action' => ['admin-action', fn () => gatesUser(RolesEnum::ROLE_ADMIN), true],
    'a Buyer, admin-action' => ['admin-action', fn () => gatesUser(RolesEnum::ROLE_BUYER), false],
    'a Supplier, admin-action' => ['admin-action', fn () => gatesUser(RolesEnum::ROLE_SUPPLIER), false],
]);

it('keeps active Suppliers out while the supplier feature is off', function () {
    config(['features.suppliers_enabled' => false]);

    expect(Gate::forUser(gatesUser(RolesEnum::ROLE_SUPPLIER))->allows('supplier-action'))->toBeFalse();
});
