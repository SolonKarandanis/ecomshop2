<?php

use App\Enums\PaymentMethodEnum;
use App\Enums\RolesEnum;
use App\Enums\UserStatusEnum;
use App\Filament\Resources\PaymentMethods\Pages\ListPaymentMethods;
use App\Models\PaymentMethod;
use App\Models\User;
use Database\Seeders\PaymentMethodSeeder;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function paymentMethodsAdmin(): User
{
    Role::firstOrCreate(['name' => RolesEnum::ROLE_ADMIN->value]);
    $admin = User::factory()->create(['status' => UserStatusEnum::ACTIVE->value]);
    $admin->assignRole(RolesEnum::ROLE_ADMIN->value);

    return $admin;
}

it('lists only the active payment methods, with translated labels, without authentication', function () {
    $this->seed(PaymentMethodSeeder::class);

    $response = $this->getJson('/api/payment-methods')->assertOk();

    expect($response->json('data'))->toBe([
        [
            'id' => PaymentMethod::where('resource_key', PaymentMethodEnum::CASH_ON_DELIVERY->value)->value('id'),
            'key' => PaymentMethodEnum::CASH_ON_DELIVERY->value,
            'label' => 'Cash on Delivery',
        ],
        [
            'id' => PaymentMethod::where('resource_key', PaymentMethodEnum::STRIPE->value)->value('id'),
            'key' => PaymentMethodEnum::STRIPE->value,
            'label' => 'Stripe',
        ],
    ]);
});

it('does not change an is_active value an admin set when the seeder runs again', function () {
    $this->seed(PaymentMethodSeeder::class);
    PaymentMethod::where('resource_key', PaymentMethodEnum::PAYPAL->value)->update(['is_active' => true]);
    PaymentMethod::where('resource_key', PaymentMethodEnum::STRIPE->value)->update(['is_active' => false]);

    $this->seed(PaymentMethodSeeder::class);

    expect(PaymentMethod::count())->toBe(3)
        ->and(PaymentMethod::where('resource_key', PaymentMethodEnum::PAYPAL->value)->value('is_active'))->toBeTrue()
        ->and(PaymentMethod::where('resource_key', PaymentMethodEnum::STRIPE->value)->value('is_active'))->toBeFalse();
});

it('serves the payment methods page to an admin', function () {
    $this->actingAs(paymentMethodsAdmin())->get('/admin/payment-methods')->assertOk();
});

it('lets an admin switch a payment method off and on in Filament, which the endpoint reflects', function () {
    $this->seed(PaymentMethodSeeder::class);
    $this->actingAs(paymentMethodsAdmin());
    $stripe = PaymentMethod::where('resource_key', PaymentMethodEnum::STRIPE->value)->first();

    Livewire::test(ListPaymentMethods::class)
        ->assertCanSeeTableRecords(PaymentMethod::all())
        ->call('updateTableColumnState', 'is_active', (string) $stripe->getKey(), false);

    expect($stripe->refresh()->is_active)->toBeFalse();
    expect(collect($this->getJson('/api/payment-methods')->json('data'))->pluck('key')->all())
        ->toBe([PaymentMethodEnum::CASH_ON_DELIVERY->value]);

    Livewire::test(ListPaymentMethods::class)
        ->call('updateTableColumnState', 'is_active', (string) $stripe->getKey(), true);

    expect($stripe->refresh()->is_active)->toBeTrue();
});
