<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

// Hostinger keeps no long-running processes alive, so a cron-driven
// `schedule:run` starts a short-lived queue worker every minute (#16).
function queueWorkerEvent(): ?Event
{
    return collect(app(Schedule::class)->events())
        ->first(fn (Event $event) => str_contains($event->command, 'queue:work'));
}

it('schedules a queue worker every minute', function () {
    $event = queueWorkerEvent();

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *');
});

it('runs the worker until the queue is empty, within the minute, with retries', function () {
    expect(queueWorkerEvent()->command)
        ->toContain('--stop-when-empty')
        ->toContain('--max-time=55')
        ->toContain('--tries=3');
});

it('never starts a second worker while one is still running', function () {
    expect(queueWorkerEvent()->withoutOverlapping)->toBeTrue();
});
