<?php

use App\Http\Controllers\Platform\Owner\CompanyController;
use App\Http\Controllers\Platform\Owner\DashboardController;
use App\Http\Controllers\Platform\Owner\PlanController;
use App\Http\Controllers\Platform\Owner\ProfileController;
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

        Route::resource('plans', PlanController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->names('plans');

        Route::get('/subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
        Route::get('/subscriptions/{subscription}', [SubscriptionController::class, 'show'])->name('subscriptions.show');

        Route::get('/queue-worker-logs', [QueueWorkerLogController::class, 'index'])->name('queue-worker-logs.index');

        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    });
