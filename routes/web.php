<?php

use App\Http\Controllers\Landing\LandingController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'index'])->name('landing');

require __DIR__.'/auth.php';
require __DIR__.'/web/landing.php';
require __DIR__.'/web/general.php';
require __DIR__.'/web/admin.php';
require __DIR__.'/web/master.php';
require __DIR__.'/web/pos.php';
require __DIR__.'/web/sale.php';
require __DIR__.'/web/purchase.php';
require __DIR__.'/web/payment.php';
require __DIR__.'/web/reports.php';
require __DIR__.'/web/stock.php';
require __DIR__.'/web/shift.php';
require __DIR__.'/web/trash.php';
require __DIR__.'/web/activity-log.php';
require __DIR__.'/web/warehouse.php';
require __DIR__.'/web/subscription.php';
