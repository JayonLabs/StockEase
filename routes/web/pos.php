<?php

use App\Http\Controllers\Payment\PaymentController;
use App\Http\Controllers\Sale\PosController;
use Illuminate\Support\Facades\Route;

Route::prefix('pos')->middleware('auth', 'role:super_admin, admin, cashier')->group(function () {
    Route::get('/', [PosController::class, 'index'])->name('pos.index');
    Route::post('/set-warehouse', [PosController::class, 'setWarehouse'])->name('pos.set-warehouse');
    Route::patch('/change-qty', [PosController::class, 'changeQty'])->name('pos.change-qty');
    Route::post('/add-to-cart', [PosController::class, 'addToCart'])->name('pos.add-to-cart');
    Route::post('/add-to-cart-barcode', [PosController::class, 'addToCartByBarcode'])->name('pos.add-to-cart-barcode');
    Route::get('/get-cart', [PosController::class, 'getCartJson'])->name('pos.get-cart');
    Route::delete('/remove-from-cart', [PosController::class, 'removeFromCart'])->name('pos.remove-from-cart');
    Route::delete('/empty-cart', [PosController::class, 'emptyCart'])->name('pos.empty-cart');
    Route::put('/checkout', [PosController::class, 'checkout'])->name('pos.checkout');
    Route::post('/send-invoice', [PosController::class, 'sendInvoice'])->name('pos.send-invoice');
    Route::post('/qris-token', [PaymentController::class, 'createMidtransTransaction'])->name('pos.qris-token');
});
