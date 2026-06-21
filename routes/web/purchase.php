<?php

use App\Http\Controllers\Purchase\PurchaseController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin, warehouse', 'plan.feature:purchasing'])->group(function () {
    Route::get('/purchase/search-supplier', [PurchaseController::class, 'searchSupplier'])->name('purchase.search-supplier');
    Route::get('/purchase/search-product', [PurchaseController::class, 'searchProduct'])->name('purchase.search-product');
    Route::resource('purchase', PurchaseController::class)->only(['index', 'store', 'update', 'destroy']);
});
