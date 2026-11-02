<?php

use App\Models\User;
use App\Services\StripeService;
use Stripe\HttpClient\CurlClient;

// Endpoints that send mail, call Stripe or build a large file turn a burst
// away with a 429 instead of letting it queue up for the PHP workers.

it('limits register, forgot and reset password to 5 a minute per IP, across the three', function () {
    foreach (range(1, 5) as $_) {
        $this->postJson('/api/forgot-password', [])->assertUnprocessable();
    }

    $this->postJson('/api/register', [])
        ->assertTooManyRequests()
        ->assertHeader('Retry-After')
        ->assertExactJson([
            'status' => false,
            'message' => 'Too many attempts. Please wait a moment and try again.',
            'data' => null,
            'error' => 'TooManyRequests',
        ]);
    $this->postJson('/api/reset-password', [])->assertTooManyRequests();
});

it('limits checkout to 10 a minute per Buyer, without slowing anyone else down', function () {
    $buyer = User::factory()->create();
    foreach (range(1, 10) as $_) {
        $this->actingAs($buyer)->postJson('/api/checkout', [])->assertUnprocessable();
    }

    $this->actingAs($buyer)->postJson('/api/checkout', [])->assertTooManyRequests();
    $this->actingAs(User::factory()->create())->postJson('/api/checkout', [])->assertUnprocessable();
});

it('limits the orders export to 5 a minute per user', function () {
    $user = User::factory()->create();
    foreach (range(1, 5) as $_) {
        $this->actingAs($user)->getJson('/api/orders/export');
    }

    $this->actingAs($user)->getJson('/api/orders/export')->assertTooManyRequests();
});

it('answers the 429 in the client\'s language', function () {
    foreach (range(1, 5) as $_) {
        $this->postJson('/api/register', []);
    }

    $this->postJson('/api/register', [], ['Accept-Language' => 'tr'])
        ->assertTooManyRequests()
        ->assertJsonPath('message', 'Çok fazla deneme yapıldı. Lütfen biraz bekleyip tekrar deneyin.');
});

it('leaves the catalogue unthrottled, since server-rendered pages all share the Nuxt server\'s IP', function () {
    foreach (range(1, 30) as $_) {
        $this->getJson('/api/categories')->assertOk();
    }
});

it('gives up on Stripe after 10 seconds, 3 to connect, instead of the SDK\'s 80 and 30', function () {
    app(StripeService::class);

    expect(CurlClient::instance()->getTimeout())->toBe(StripeService::TIMEOUT_SECONDS)
        ->and(CurlClient::instance()->getConnectTimeout())->toBe(StripeService::CONNECT_TIMEOUT_SECONDS)
        ->and(StripeService::TIMEOUT_SECONDS)->toBe(10)
        ->and(StripeService::CONNECT_TIMEOUT_SECONDS)->toBe(3);
});
