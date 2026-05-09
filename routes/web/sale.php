<?php

use App\Http\Controllers\Sale\SaleHistoryController;
use App\Http\Controllers\Sale\SaleReturnController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth', 'role:admin, cashier')->group(function () {
    Route::get('/sale', [SaleHistoryController::class, 'index'])->name('sale.index');
    Route::get('/sale/{sale}', [SaleHistoryController::class, 'show'])->name('sale.show');
    Route::get('/sale/{sale}/export-to-pdf', [SaleHistoryController::class, 'exportToPdf'])->name('sale.export-to-pdf');

    // Sale Return
    Route::get('/sale-return', [SaleReturnController::class, 'index'])->name('sale-return.index');
    Route::get('/sale-return/{saleReturn}/detail', [SaleReturnController::class, 'detail'])->name('sale-return.detail');
    Route::get('/sale-return/{sale}/create', [SaleReturnController::class, 'show'])->name('sale-return.show');
    Route::post('/sale-return/{sale}', [SaleReturnController::class, 'store'])->name('sale-return.store');
});
