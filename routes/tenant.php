<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByRequestData;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant routes for your application.
| These routes are loaded by the TenancyServiceProvider.
|
| Uses request-based tenant identification (header X-Tenant or ?tenant= query param)
| instead of domain-based identification.
|
*/

Route::middleware([
    'web',
    InitializeTenancyByRequestData::class,
])->group(function () {
    // Tenant-specific routes go here.
    // These are only activated when a tenant is identified via request data.
    // For single-database tenancy, all business routes are in web.php
    // and scoped via the BelongsToTenant global scope.
});
