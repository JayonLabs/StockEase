<?php

use App\Http\Controllers\Product\PriceController;
use App\Http\Controllers\Product\ProductController;
use App\Http\Controllers\Product\UnitController;
use App\Http\Controllers\Purchase\SupplierController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin, warehouse'])->group(function () {
    Route::resource('supplier', SupplierController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('product/{product}/price', [PriceController::class, 'edit'])->name('product.price.edit');
    Route::patch('product/{product}/price', [PriceController::class, 'update'])->name('product.price.update');

    Route::resource('product', ProductController::class);
    Route::resource('unit', UnitController::class);

    Route::post('/products', [ProductController::class, 'store'])
        ->middleware('subscription.limit:product')
        ->name('products.store');
});
