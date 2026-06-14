<?php

use App\Http\Controllers\Platform\Owner\CompanyController;
use App\Http\Controllers\Platform\Owner\DashboardController;
use App\Http\Controllers\Platform\Owner\PlanController;
use App\Http\Controllers\Platform\Owner\QueueWorkerLogController;
use App\Http\Controllers\Platform\Owner\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:platform_owner', 'ensure.tenancy.ended'])
    ->prefix('platform/owner')
    ->name('platform.owner.')
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/companies', [CompanyController::class, 'index'])->name('companies.index');
        Route::get('/companies/{company}', [CompanyController::class, 'show'])->name('companies.show');

        Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');

        Route::get('/subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
        Route::get('/subscriptions/{subscription}', [SubscriptionController::class, 'show'])->name('subscriptions.show');

        Route::get('/queue-worker-logs', [QueueWorkerLogController::class, 'index'])->name('queue-worker-logs.index');
    });
