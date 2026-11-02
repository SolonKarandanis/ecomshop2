<?php

use App\Exceptions\EmptyCartException;
use App\Exceptions\PaymentException;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;

// How the API answers a failure, and which failures reach the log: an
// ApiException is the client's answer, anything else is a bug or an outage.

beforeEach(function () {
    Exceptions::fake();
    config(['app.debug' => false]);
});

function failingRoute(Throwable $exception): string
{
    $uri = '/api/__test/fails-'.md5(get_class($exception).$exception->getMessage());
    Route::middleware('api')->get($uri, fn () => throw $exception);

    return $uri;
}

it('answers an unexpected failure with a generic 500 in the API\'s error shape, and reports it', function () {
    $this->getJson(failingRoute(new RuntimeException('the database went away')))
        ->assertStatus(500)
        ->assertExactJson([
            'status' => false,
            'message' => 'Something went wrong on our side. Please try again in a moment.',
            'data' => null,
            'error' => 'ServerError',
        ]);

    Exceptions::assertReported(fn (RuntimeException $e) => $e->getMessage() === 'the database went away');
});

it('gives the generic 500 message in the client\'s language', function () {
    $this->getJson(failingRoute(new TypeError('a bug')), ['Accept-Language' => 'el'])
        ->assertStatus(500)
        ->assertJsonPath('message', 'Κάτι πήγε στραβά από τη δική μας πλευρά. Δοκιμάστε ξανά σε λίγο.');
});

it('keeps Laravel\'s debug output for a 500 when APP_DEBUG is on', function () {
    config(['app.debug' => true]);

    $this->getJson(failingRoute(new RuntimeException('show me the trace')))
        ->assertStatus(500)
        ->assertJsonPath('message', 'show me the trace')
        ->assertJsonStructure(['exception', 'trace']);
});

it('answers a client error with its own message and status, without logging it', function () {
    $this->getJson(failingRoute(EmptyCartException::emptyCart()))
        ->assertBadRequest()
        ->assertExactJson([
            'status' => false,
            'message' => 'Cart is empty',
            'data' => null,
            'error' => 'EmptyCartException',
        ]);

    Exceptions::assertNothingReported();
});

it('reports a server-side ApiException, keeping the error that caused it', function () {
    $stripeError = new RuntimeException('Stripe is down');

    $this->getJson(failingRoute(PaymentException::createStipeSession($stripeError)))
        ->assertStatus(502)
        ->assertJsonPath('error', 'PaymentException');

    Exceptions::assertReported(fn (PaymentException $e) => $e->getPrevious() === $stripeError);
});
