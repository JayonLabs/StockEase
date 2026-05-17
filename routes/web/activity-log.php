<?php

use App\Http\Controllers\General\ActivityLogController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])
        ->middleware('can:view_activity_logs')
        ->name('activity-logs.index');

    Route::get('/activity-logs/{activity}', [ActivityLogController::class, 'show'])
        ->middleware('can:view_activity_logs')
        ->name('activity-logs.show');
});
