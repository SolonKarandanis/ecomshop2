<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

// Production stores sessions in the database (SESSION_DRIVER=database). Expired rows
// are deleted by a scheduled session:garbage-collect (Laravel 13.39+) instead of the
// per-request lottery, which is turned off.

it('schedules the session garbage collector every hour', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event) => str_contains($event->command, 'session:garbage-collect'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 * * * *');
});

it('turns off the per-request session lottery, since the scheduler does the clean-up', function () {
    expect(config('session.lottery')[0])->toBe(0);
});

it('deletes database sessions older than the session lifetime, and keeps active ones', function () {
    config(['session.driver' => 'database', 'session.lifetime' => 120]);
    $row = fn (string $id, int $minutesAgo) => [
        'id' => $id,
        'user_id' => null,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
        'payload' => '',
        'last_activity' => now()->subMinutes($minutesAgo)->getTimestamp(),
    ];
    DB::table('sessions')->insert([$row('expired', 121), $row('active', 30)]);

    Artisan::call('session:garbage-collect');

    expect(DB::table('sessions')->pluck('id')->all())->toBe(['active']);
});
