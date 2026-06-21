<?php

use App\Http\Controllers\General\DashboardController;
use App\Http\Controllers\Media\FileManagerController;
use App\Http\Controllers\User\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/profile/photo-profile', [ProfileController::class, 'storePhotoProfile'])->name('profile.photo-profile');
    Route::delete('/profile/photo-profile', [ProfileController::class, 'destroyPhotoProfile'])->name('profile.destroy-photo-profile');

    Route::prefix('file-manager')->name('file-manager.')->middleware('plan.feature:file_manager')->group(function () {
        Route::get('/', [FileManagerController::class, 'index'])->middleware('can:view_file_manager')->name('index');
        Route::get('/download', [FileManagerController::class, 'download'])->middleware('can:download_files')->name('download');
        Route::delete('/', [FileManagerController::class, 'destroy'])->middleware('can:delete_files')->name('destroy');
        Route::post('/upload', [FileManagerController::class, 'store'])->middleware('can:upload_files')->name('store');
    });

});
