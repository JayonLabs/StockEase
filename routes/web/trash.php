<?php

use App\Http\Controllers\Trash\TrashController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/trash', [TrashController::class, 'index'])->name('trash.index');
    Route::get('/trash/{type}/{id}', [TrashController::class, 'show'])->name('trash.show');
    Route::post('/trash/restore', [TrashController::class, 'restore'])->name('trash.restore');
    Route::delete('/trash/force', [TrashController::class, 'forceDestroy'])->name('trash.force-destroy');
});
