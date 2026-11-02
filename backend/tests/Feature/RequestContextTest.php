<?php

use App\Http\Middleware\AddRequestContext;
use App\Models\User;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

// Context outlives a request in tests (nothing flushes it between them), so
// each test starts empty and reads what its own request left behind.
beforeEach(function () {
    Context::flush();
});

it('gives every API response a trace ID, and tags the request with it', function () {
    $response = $this->getJson('/api/config')->assertOk();

    $traceId = $response->headers->get(AddRequestContext::HEADER);

    expect(Str::isUuid($traceId))->toBeTrue()
        ->and(Context::get('trace_id'))->toBe($traceId)
        ->and(Context::get('method'))->toBe('GET');
});

it('tags the request with its route name', function () {
    $this->postJson('/api/stripe/webhook', [])->assertStatus(400);

    expect(Context::get('route'))->toBe('stripe.webhook');
});

it('reuses a caller\'s trace ID when it is a UUID', function () {
    $traceId = (string) Str::uuid();

    $this->getJson('/api/config', [AddRequestContext::HEADER => $traceId])
        ->assertHeader(AddRequestContext::HEADER, $traceId);

    expect(Context::get('trace_id'))->toBe($traceId);
});

it('replaces a caller\'s trace ID that is not a UUID', function () {
    $response = $this->getJson('/api/config', [AddRequestContext::HEADER => "anything\nelse"]);

    $traceId = $response->headers->get(AddRequestContext::HEADER);

    expect($traceId)->not->toBe("anything\nelse")
        ->and(Str::isUuid($traceId))->toBeTrue();
});

it('gives the admin panel a trace ID too', function () {
    $this->get('/admin/login')
        ->assertOk()
        ->assertHeader(AddRequestContext::HEADER);
});

it('tags the request with the user who logs in', function () {
    $user = User::factory()->create(['password' => Hash::make('secret')]);

    // From the storefront's origin, so Sanctum gives the request a session.
    $this->withHeader('Origin', 'http://localhost:3000')->postJson('/api/login', ['email' => $user->email, 'password' => 'secret'])->assertOk();

    expect(Context::get('user_id'))->toBe($user->id);
});

it('leaves a guest\'s request without a user', function () {
    $this->getJson('/api/config')->assertOk();

    expect(Context::has('user_id'))->toBeFalse();
});

it('carries the request\'s context into the jobs it dispatches', function () {
    $carried = null;
    Event::listen(JobProcessing::class, function (JobProcessing $event) use (&$carried) {
        $carried = $event->job->payload()['illuminate:log:context']['data'] ?? null;
    });

    Context::add(['trace_id' => 'trace-123', 'order_id' => 42]);
    dispatch(function () {});

    expect($carried)->not->toBeNull()
        ->and(unserialize($carried['trace_id']))->toBe('trace-123')
        ->and(unserialize($carried['order_id']))->toBe(42);
});
