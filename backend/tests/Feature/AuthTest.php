<?php

use App\Exceptions\OrderException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

it('serves a CSRF cookie', function () {
    $this->get('/sanctum/csrf-cookie')
        ->assertNoContent()
        ->assertCookie('XSRF-TOKEN');
});

it('logs a user in with valid credentials and recognizes the session on later requests', function () {
    $user = User::factory()->create(['password' => Hash::make('password')]);

    $this->withHeader('Origin', 'http://localhost:3000')
        ->postJson('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()->assertJsonPath('data.email', $user->email);

    $this->withHeader('Origin', 'http://localhost:3000')
        ->getJson('/user')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);
});

it('rejects login with invalid credentials with a 422 field error', function () {
    $user = User::factory()->create(['password' => Hash::make('password')]);

    $this->postJson('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('rejects login with missing fields with a 422 field error', function () {
    $this->postJson('/login', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);
});

it('logs a user out and invalidates the session', function () {
    $user = User::factory()->create(['password' => Hash::make('password')]);

    $client = $this->withHeader('Origin', 'http://localhost:3000');

    $client->postJson('/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
    $client->postJson('/logout')->assertNoContent();

    // A real request re-resolves guards from scratch; the in-process test
    // container caches guard instances across chained calls, so drop the
    // cache to simulate that fresh resolution.
    $this->app['auth']->forgetGuards();

    $client->getJson('/user')->assertUnauthorized();
});

it('returns 401 from the who-am-i endpoint when unauthenticated', function () {
    $this->getJson('/user')->assertUnauthorized();
});

it('maps a domain exception to its declared status code and a consistent JSON shape', function () {
    Route::middleware('api')->get('/__test/order-exception', function () {
        throw OrderException::checkout();
    });

    $this->getJson('/__test/order-exception')
        ->assertBadRequest()
        ->assertExactJson(['message' => OrderException::checkout()->getMessage()]);
});
