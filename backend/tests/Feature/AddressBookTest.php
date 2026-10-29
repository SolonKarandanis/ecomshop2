<?php

use App\Enums\RolesEnum;
use App\Models\Address;
use App\Models\Order;
use App\Models\SavedAddress;
use App\Models\User;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;

function addressBookBuyer(): User
{
    Role::firstOrCreate(['name' => RolesEnum::ROLE_BUYER->value]);
    $buyer = User::factory()->create();
    $buyer->assignRole(RolesEnum::ROLE_BUYER->value);

    return $buyer;
}

function addressBookPayload(array $overrides = []): array
{
    return [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'phone' => '555-1234',
        'street_address' => '1 Main St',
        'city' => 'Athens',
        'country' => 'Greece',
        'postal_code' => '12345',
        ...$overrides,
    ];
}

it("lists only the User's own saved addresses, default first", function () {
    $buyer = addressBookBuyer();
    $older = SavedAddress::factory()->for($buyer)->create(['city' => 'Patras', 'updated_at' => Carbon::now()->subDays(2)]);
    $default = SavedAddress::factory()->for($buyer)->default()->create(['city' => 'Athens', 'updated_at' => Carbon::now()->subDays(3)]);
    $newer = SavedAddress::factory()->for($buyer)->create(['city' => 'Sparta', 'updated_at' => Carbon::now()->subDay()]);
    SavedAddress::factory()->for(addressBookBuyer())->default()->create(['city' => 'Elsewhere']);

    $response = $this->actingAs($buyer)->getJson('/api/profile/addresses')->assertOk();

    expect($response->json('data.*.id'))->toBe([$default->id, $newer->id, $older->id]);
    expect($response->json('data.0'))->toBe([
        'id' => $default->id,
        'first_name' => $default->first_name,
        'last_name' => $default->last_name,
        'phone' => $default->phone,
        'street_address' => $default->street_address,
        'city' => 'Athens',
        'country' => $default->country,
        'postal_code' => $default->postal_code,
        'is_default' => true,
    ]);
});

it("doesn't list past Order addresses in the address book", function () {
    $buyer = addressBookBuyer();
    $order = Order::factory()->create(['user_id' => $buyer->id]);
    Address::create(['user_id' => $buyer->id, 'order_id' => $order->id, ...addressBookPayload()]);

    $this->actingAs($buyer)->getJson('/api/profile/addresses')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it("makes a User's first saved address the default, and later ones not", function () {
    $buyer = addressBookBuyer();

    $first = $this->actingAs($buyer)->postJson('/api/profile/addresses', addressBookPayload())
        ->assertCreated()
        ->assertJsonPath('data.city', 'Athens')
        ->assertJsonPath('data.is_default', true);

    $this->actingAs($buyer)->postJson('/api/profile/addresses', addressBookPayload(['city' => 'Sparta']))
        ->assertCreated()
        ->assertJsonPath('data.is_default', false);

    expect(SavedAddress::where('user_id', $buyer->id)->where('is_default', true)->pluck('id')->all())
        ->toBe([$first->json('data.id')]);
});

it('rejects an invalid address with per-field 422s', function () {
    $buyer = addressBookBuyer();

    $this->actingAs($buyer)->postJson('/api/profile/addresses', addressBookPayload(['first_name' => '', 'postal_code' => 'x']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['first_name', 'postal_code']);

    $this->assertDatabaseCount('saved_addresses', 0);
});

it('updates only the fields sent', function () {
    $buyer = addressBookBuyer();
    $address = SavedAddress::factory()->for($buyer)->default()->create(addressBookPayload());

    $this->actingAs($buyer)->patchJson("/api/profile/addresses/{$address->id}", ['city' => 'Thessaloniki'])
        ->assertOk()
        ->assertJsonPath('data.city', 'Thessaloniki')
        ->assertJsonPath('data.street_address', '1 Main St')
        ->assertJsonPath('data.is_default', true);

    $this->actingAs($buyer)->patchJson("/api/profile/addresses/{$address->id}", ['phone' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone']);
});

it('makes an address the default and unsets the previous one', function () {
    $buyer = addressBookBuyer();
    $previous = SavedAddress::factory()->for($buyer)->default()->create();
    $address = SavedAddress::factory()->for($buyer)->create();

    $this->actingAs($buyer)->postJson("/api/profile/addresses/{$address->id}/default")
        ->assertOk()
        ->assertJsonPath('data.id', $address->id)
        ->assertJsonPath('data.is_default', true);

    expect($previous->fresh()->is_default)->toBeFalse();
    expect($address->fresh()->is_default)->toBeTrue();
});

it('promotes the most recently updated remaining address when the default is deleted', function () {
    $buyer = addressBookBuyer();
    $default = SavedAddress::factory()->for($buyer)->default()->create();
    $stale = SavedAddress::factory()->for($buyer)->create(['updated_at' => Carbon::now()->subDays(5)]);
    $recent = SavedAddress::factory()->for($buyer)->create(['updated_at' => Carbon::now()->subDay()]);

    $this->actingAs($buyer)->deleteJson("/api/profile/addresses/{$default->id}")->assertNoContent();

    $this->assertModelMissing($default);
    expect($recent->fresh()->is_default)->toBeTrue();
    expect($stale->fresh()->is_default)->toBeFalse();
});

it('keeps the default when a non-default address is deleted', function () {
    $buyer = addressBookBuyer();
    $default = SavedAddress::factory()->for($buyer)->default()->create();
    $other = SavedAddress::factory()->for($buyer)->create(['updated_at' => Carbon::now()->addMinute()]);

    $this->actingAs($buyer)->deleteJson("/api/profile/addresses/{$other->id}")->assertNoContent();

    expect($default->fresh()->is_default)->toBeTrue();
});

it('deletes the last address, leaving no default', function () {
    $buyer = addressBookBuyer();
    $only = SavedAddress::factory()->for($buyer)->default()->create();

    $this->actingAs($buyer)->deleteJson("/api/profile/addresses/{$only->id}")->assertNoContent();

    $this->assertDatabaseCount('saved_addresses', 0);
});

it("returns 404 for another User's address, without changing it", function () {
    $buyer = addressBookBuyer();
    $theirs = SavedAddress::factory()->for(addressBookBuyer())->create(['city' => 'Sparta']);

    $this->actingAs($buyer)->patchJson("/api/profile/addresses/{$theirs->id}", ['city' => 'Athens'])->assertNotFound();
    $this->actingAs($buyer)->postJson("/api/profile/addresses/{$theirs->id}/default")->assertNotFound();
    $this->actingAs($buyer)->deleteJson("/api/profile/addresses/{$theirs->id}")->assertNotFound();

    expect($theirs->fresh())
        ->city->toBe('Sparta')
        ->is_default->toBeFalse();
});

it("doesn't change an Order's address when a saved address is edited or deleted", function () {
    $buyer = addressBookBuyer();
    $order = Order::factory()->create(['user_id' => $buyer->id]);
    $orderAddress = Address::create(['user_id' => $buyer->id, 'order_id' => $order->id, ...addressBookPayload()]);
    $saved = SavedAddress::factory()->for($buyer)->default()->create(addressBookPayload());

    $this->actingAs($buyer)->patchJson("/api/profile/addresses/{$saved->id}", ['city' => 'Sparta'])->assertOk();
    $this->actingAs($buyer)->deleteJson("/api/profile/addresses/{$saved->id}")->assertNoContent();

    expect($orderAddress->fresh())->city->toBe('Athens');
});

it('rejects a guest from every address book endpoint', function () {
    $address = SavedAddress::factory()->create();

    $this->getJson('/api/profile/addresses')->assertUnauthorized();
    $this->postJson('/api/profile/addresses', addressBookPayload())->assertUnauthorized();
    $this->patchJson("/api/profile/addresses/{$address->id}", ['city' => 'x'])->assertUnauthorized();
    $this->deleteJson("/api/profile/addresses/{$address->id}")->assertUnauthorized();
    $this->postJson("/api/profile/addresses/{$address->id}/default")->assertUnauthorized();
});
