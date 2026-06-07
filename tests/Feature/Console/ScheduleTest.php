<?php

use Illuminate\Console\Scheduling\Schedule;

it('schedules queue worker command', function () {
    /** @var Schedule $schedule */
    $schedule = app(Schedule::class);

    $events = collect($schedule->events())
        ->filter(fn ($event) => str_contains($event->command ?? '', 'queue:work'));

    expect($events)->toHaveCount(1);
});

it('runs queue worker every minute', function () {
    /** @var Schedule $schedule */
    $schedule = app(Schedule::class);

    $event = collect($schedule->events())
        ->first(fn ($event) => str_contains($event->command ?? '', 'queue:work'));

    expect($event->expression)->toBe('* * * * *');
});

it('prevents overlapping queue worker with 10-minute lock', function () {
    /** @var Schedule $schedule */
    $schedule = app(Schedule::class);

    $event = collect($schedule->events())
        ->first(fn ($event) => str_contains($event->command ?? '', 'queue:work'));

    expect($event->withoutOverlapping)->toBeTrue();
    expect($event->expiresAt)->toBe(10);
});

it('restricts queue worker to production environment', function () {
    /** @var Schedule $schedule */
    $schedule = app(Schedule::class);

    $event = collect($schedule->events())
        ->first(fn ($event) => str_contains($event->command ?? '', 'queue:work'));

    expect($event->environments)->toBe(['production']);
});

it('has descriptive description', function () {
    /** @var Schedule $schedule */
    $schedule = app(Schedule::class);

    $event = collect($schedule->events())
        ->first(fn ($event) => str_contains($event->command ?? '', 'queue:work'));

    expect($event->description)->toContain('shared hosting');
});

it('passes stop-when-empty flag', function () {
    /** @var Schedule $schedule */
    $schedule = app(Schedule::class);

    $event = collect($schedule->events())
        ->first(fn ($event) => str_contains($event->command ?? '', 'queue:work'));

    expect($event->command)->toContain('--stop-when-empty');
});

it('passes sleep 10 seconds flag to reduce CPU', function () {
    /** @var Schedule $schedule */
    $schedule = app(Schedule::class);

    $event = collect($schedule->events())
        ->first(fn ($event) => str_contains($event->command ?? '', 'queue:work'));

    expect($event->command)->toContain('--sleep=10');
});

it('passes timeout 30 seconds flag', function () {
    /** @var Schedule $schedule */
    $schedule = app(Schedule::class);

    $event = collect($schedule->events())
        ->first(fn ($event) => str_contains($event->command ?? '', 'queue:work'));

    expect($event->command)->toContain('--timeout=30');
});

it('passes max-time 50 seconds flag', function () {
    /** @var Schedule $schedule */
    $schedule = app(Schedule::class);

    $event = collect($schedule->events())
        ->first(fn ($event) => str_contains($event->command ?? '', 'queue:work'));

    expect($event->command)->toContain('--max-time=50');
});

it('passes max-jobs 10 flag to limit per cron run', function () {
    /** @var Schedule $schedule */
    $schedule = app(Schedule::class);

    $event = collect($schedule->events())
        ->first(fn ($event) => str_contains($event->command ?? '', 'queue:work'));

    expect($event->command)->toContain('--max-jobs=10');
});

it('writes output to log file', function () {
    /** @var Schedule $schedule */
    $schedule = app(Schedule::class);

    $event = collect($schedule->events())
        ->first(fn ($event) => str_contains($event->command ?? '', 'queue:work'));

    expect($event->output)->toContain('queue-worker.log');
});

it('has scheduled tasks', function () {
    /** @var Schedule $schedule */
    $schedule = app(Schedule::class);

    expect(count($schedule->events()))->toBeGreaterThanOrEqual(1);
});
