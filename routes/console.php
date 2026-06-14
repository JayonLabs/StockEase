<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('queue:work --stop-when-empty --sleep=10 --timeout=30 --max-time=50 --max-jobs=10')
    ->everyMinute()
    ->withoutOverlapping(10)
    ->environments(['production'])
    ->sendOutputTo(storage_path('logs/queue-worker.log'))
    ->description('Process queued jobs safely on shared hosting with CPU limits');

Schedule::command('subscription:downgrade-expired')->daily();

Schedule::command('platform:snapshot')->dailyAt('23:55');
