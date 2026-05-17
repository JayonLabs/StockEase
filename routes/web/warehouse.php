<?php

use App\Http\Controllers\Warehouse\StockTransferController;
use App\Http\Controllers\Warehouse\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin, warehouse'])->group(function () {
    Route::resource('warehouse', WarehouseController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('/stock-transfer', [StockTransferController::class, 'index'])->name('stock-transfer.index');
    Route::post('/stock-transfer', [StockTransferController::class, 'store'])->name('stock-transfer.store');
    Route::get('/stock-transfer/search-product', [StockTransferController::class, 'searchProduct'])->name('stock-transfer.search-product');
});
