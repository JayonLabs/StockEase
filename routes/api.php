<?php

use App\Http\Controllers\Payment\PaymentController;
use App\Http\Controllers\Stock\StockAlertController;
use App\Http\Controllers\User\NotificationController;
use Illuminate\Support\Facades\Route;

// Webhook notification midtrans
Route::post('/midtrans/notification', [PaymentController::class, 'midtransNotification'])->name('midtrans.notification');

// Notification routes - requires authentication
Route::middleware('auth:sanctum')->group(function () {
    // Alert/Notification Stock Route - admin & warehouse only
    Route::get('/low-stock', [StockAlertController::class, 'index'])
        ->middleware('role:admin,warehouse')
        ->name('low-stock.index');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
});
