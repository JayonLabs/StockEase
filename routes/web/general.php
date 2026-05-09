<?php

use App\Http\Controllers\General\DashboardController;
use App\Http\Controllers\General\QueueWorkerLogController;
use App\Http\Controllers\Media\FileManagerController;
use App\Http\Controllers\User\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/profile/photo-profile', [ProfileController::class, 'storePhotoProfile'])->name('profile.photo-profile');
    Route::delete('/profile/photo-profile', [ProfileController::class, 'destroyPhotoProfile'])->name('profile.destroy-photo-profile');

    Route::get('/file-manager', [FileManagerController::class, 'index'])->name('file-manager.index');
    Route::get('/file-manager/download', [FileManagerController::class, 'download'])->name('file-manager.download');
    Route::delete('/file-manager', [FileManagerController::class, 'destroy'])->name('file-manager.destroy');
    Route::post('/file-manager/upload', [FileManagerController::class, 'store'])->name('file-manager.store');

    Route::get('/queue-worker-logs', [QueueWorkerLogController::class, 'index'])
        ->middleware('role:admin')
        ->name('queue-worker-logs.index');
});
