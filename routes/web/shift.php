<?php

use App\Http\Controllers\Shift\ShiftController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth', 'role:super_admin, admin, cashier')->group(function () {
    Route::get('/shift', [ShiftController::class, 'index'])->name('shift.index');
    Route::get('/shift/{shift}', [ShiftController::class, 'show'])->name('shift.show');
    Route::post('/shift', [ShiftController::class, 'store'])->name('shift.store');
    Route::post('/shift/{shift}/close', [ShiftController::class, 'close'])->name('shift.close');
});
