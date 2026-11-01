<?php

use App\Enums\PaymentMethodEnum;
use App\Enums\RolesEnum;
use App\Enums\UserStatusEnum;
use App\Models\Attribute;
use App\Models\PaymentMethod;
use App\Models\User;
use Database\Seeders\PaymentMethodSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\App;
use Spatie\Permission\Models\Role;

function localeUser(RolesEnum $role, array $attributes = []): User
{
    Role::firstOrCreate(['name' => $role->value]);
    $user = User::factory()->create(['status' => UserStatusEnum::ACTIVE->value, ...$attributes]);
    $user->assignRole($role->value);

    return $user;
}

function codLabel(?string $acceptLanguage): string
{
    $headers = $acceptLanguage === null ? [] : ['Accept-Language' => $acceptLanguage];

    return collect(test()->getJson('/api/payment-methods', $headers)->assertOk()->json('data'))
        ->firstWhere('key', PaymentMethodEnum::CASH_ON_DELIVERY->value)['label'];
}

it('answers in the language asked for in Accept-Language', function (?string $header, string $label) {
    $this->seed(PaymentMethodSeeder::class);

    expect(codLabel($header))->toBe($label);
})->with([
    'Greek' => ['el', 'Αντικαταβολή'],
    'Turkish' => ['tr', 'Kapıda ödeme'],
    'English' => ['en', 'Cash on Delivery'],
    'a regional variant' => ['el-GR,el;q=0.9', 'Αντικαταβολή'],
    'the first supported language in the list' => ['fr-FR, fr;q=0.9, tr;q=0.8, en;q=0.5', 'Kapıda ödeme'],
    'an unsupported language' => ['fr', 'Cash on Delivery'],
    'no header' => [null, 'Cash on Delivery'],
]);

it('translates attribute labels per request', function () {
    Attribute::create(['name' => 'attribute.color', 'type' => 'Image']);

    $this->getJson('/api/attributes', ['Accept-Language' => 'el'])->assertJsonPath('data.0.label', 'Χρώμα');
    $this->getJson('/api/attributes', ['Accept-Language' => 'tr'])->assertJsonPath('data.0.label', 'Renk');
    $this->getJson('/api/attributes')->assertJsonPath('data.0.label', 'Color');
});

it('returns domain exceptions in the requested language', function (string $header, string $message) {
    PaymentMethod::firstOrCreate(['resource_key' => PaymentMethodEnum::CASH_ON_DELIVERY->value]);

    $this->actingAs(localeUser(RolesEnum::ROLE_BUYER))
        ->postJson('/api/checkout', [
            'firstName' => 'Jane',
            'lastName' => 'Doe',
            'phone' => '555-1234',
            'address' => '1 Main St',
            'city' => 'Athens',
            'country' => 'Greece',
            'zipCode' => '12345',
            'paymentMethod' => PaymentMethodEnum::CASH_ON_DELIVERY->value,
        ], ['Accept-Language' => $header])
        ->assertBadRequest()
        ->assertJsonPath('message', $message);
})->with([
    'Greek' => ['el', 'Το καλάθι είναι άδειο'],
    'Turkish' => ['tr', 'Sepet boş'],
    'unsupported' => ['fr', 'Cart is empty'],
]);

it('returns validation errors in the requested language, with translated field names', function (string $header, string $message) {
    $this->postJson('/api/login', ['email' => 'jane@example.com'], ['Accept-Language' => $header])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password' => $message]);
})->with([
    'Greek' => ['el', 'Το πεδίο συνθηματικό είναι απαραίτητο.'],
    'Turkish' => ['tr', 'Parola mutlaka gereklidir.'],
    'English' => ['en', 'The password field is required.'],
]);

it("saves a supported locale on the user's profile and returns it", function () {
    $buyer = localeUser(RolesEnum::ROLE_BUYER);
    expect($buyer->fresh()->locale)->toBe('en');

    $this->actingAs($buyer)
        ->patchJson('/api/profile', ['name' => $buyer->name, 'email' => $buyer->email, 'locale' => 'el'])
        ->assertOk()
        ->assertJsonPath('data.locale', 'el');

    expect($buyer->fresh()->locale)->toBe('el');
    $this->getJson('/api/profile')->assertJsonPath('data.locale', 'el');
    $this->getJson('/api/user')->assertJsonPath('data.locale', 'el');
});

it('keeps the saved locale when a profile update leaves it out', function () {
    $buyer = localeUser(RolesEnum::ROLE_BUYER, ['locale' => 'tr']);

    $this->actingAs($buyer)
        ->patchJson('/api/profile', ['name' => 'Renamed', 'email' => $buyer->email])
        ->assertOk()
        ->assertJsonPath('data.locale', 'tr');
});

it('rejects an unsupported locale', function () {
    $buyer = localeUser(RolesEnum::ROLE_BUYER);

    $this->actingAs($buyer)
        ->patchJson('/api/profile', ['name' => $buyer->name, 'email' => $buyer->email, 'locale' => 'fr'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('locale');

    expect($buyer->fresh()->locale)->toBe('en');
});

it("prefers the user's saved locale for Mail and Notifications", function () {
    expect(localeUser(RolesEnum::ROLE_BUYER, ['locale' => 'el'])->preferredLocale())->toBe('el');
});

it("shows the admin panel in the admin's saved language", function (string $locale, string $title) {
    $this->actingAs(localeUser(RolesEnum::ROLE_ADMIN, ['locale' => $locale]))
        ->get('/admin')
        ->assertOk()
        ->assertSee('lang="'.$locale.'"', false)
        ->assertSee($title);
})->with([
    'Greek' => ['el', 'Πίνακας ελέγχου'],
    'Turkish' => ['tr', 'Panel'],
    'English' => ['en', 'Dashboard'],
]);

it('translates the password reset email, subject included', function (string $locale, string $subject, string $button) {
    App::setLocale($locale);

    $mail = (new ResetPassword('token'))->toMail(localeUser(RolesEnum::ROLE_BUYER));

    expect($mail->subject)->toBe($subject)
        ->and($mail->actionText)->toBe($button);
})->with([
    'Greek' => ['el', 'Επαναφορά του κωδικού πρόσβασής σας', 'Επαναφορά Κωδικού'],
    'Turkish' => ['tr', 'Parolanızı sıfırlayın', 'Parolayı Sıfırla'],
    'English' => ['en', 'Reset your password', 'Reset Password'],
]);
