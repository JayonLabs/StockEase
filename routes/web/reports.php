<?php

use App\Http\Controllers\Purchase\PurchaseReportController;
use App\Http\Controllers\Report\ProductMovementController;
use App\Http\Controllers\Report\ProfitLossReportController;
use App\Http\Controllers\Sale\SaleReportController;
use App\Http\Controllers\Stock\ExpiryReportController;
use App\Http\Controllers\Stock\StockReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('reports')->group(function () {
    Route::middleware('auth', 'role:admin, cashier')->group(function () {
        Route::get('/sale', [SaleReportController::class, 'index'])->name('reports.sale.index');
        Route::get('/sale/search-cashier', [SaleReportController::class, 'searchCashier'])->name('reports.sale.search-cashier');
        Route::get('/sale/export-to-pdf', [SaleReportController::class, 'exportToPdf'])->name('reports.sale.export-to-pdf');
        Route::get('/sale/export-to-excel', [SaleReportController::class, 'exportToExcel'])->name('reports.sale.export-to-excel');
    });

    Route::middleware('auth', 'role:admin, warehouse', 'plan.feature:purchase_report')->group(function () {
        Route::get('/purchase', [PurchaseReportController::class, 'index'])->name('reports.purchase.index');
        Route::get('/purchase/search-supplier', [PurchaseReportController::class, 'searchSupplier'])->name('reports.purchase.search-supplier');
        Route::get('/purchase/search-user', [PurchaseReportController::class, 'searchUser'])->name('reports.purchase.search-user');
        Route::get('/purchase/export-to-pdf', [PurchaseReportController::class, 'exportToPdf'])->name('reports.purchase.export-to-pdf');
        Route::get('/purchase/export-to-excel', [PurchaseReportController::class, 'exportToExcel'])->name('reports.purchase.export-to-excel');
    });

    Route::middleware('auth', 'role:admin, warehouse', 'plan.feature:stock_report')->group(function () {
        Route::get('/stock', [StockReportController::class, 'index'])->name('reports.stock.index');
        Route::get('/stock/searchCategory', [StockReportController::class, 'searchCategory'])->name('reports.stock.searchCategory');
        Route::get('/stock/searchSupplier', [StockReportController::class, 'searchSupplier'])->name('reports.stock.searchSupplier');
        Route::get('/stock/export-to-pdf', [StockReportController::class, 'exportToPdf'])->name('reports.stock.export-to-pdf');
        Route::get('/stock/export-to-excel', [StockReportController::class, 'exportToExcel'])->name('reports.stock.export-to-excel');

        Route::get('/expiry', [ExpiryReportController::class, 'index'])->name('reports.expiry.index');
    });

    Route::middleware('auth', 'role:admin')->group(function () {
        Route::get('/profit-loss', [ProfitLossReportController::class, 'index'])
            ->middleware('plan.feature:profit_loss')
            ->name('reports.profit-loss');
        Route::get('/product-movement', [ProductMovementController::class, 'index'])->name('reports.product-movement');
    });
});
