<?php

use App\Http\Middleware\EnsureTenancyEnded;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Route::middleware(['web', 'auth', EnsureTenancyEnded::class])
        ->get('/_test/ensure-tenancy-ended', function () {
            return ['tenant_initialized' => tenancy()->initialized];
        })->name('_test.ensure-tenancy-ended');
});

it('ends tenancy if initialized', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);

    tenancy()->initialize($company);

    actingAs($user)
        ->get('/_test/ensure-tenancy-ended')
        ->assertOk()
        ->assertJson(['tenant_initialized' => false]);
});

it('does nothing if tenancy not initialized', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);

    actingAs($user)
        ->get('/_test/ensure-tenancy-ended')
        ->assertOk()
        ->assertJson(['tenant_initialized' => false]);
});

it('allows global queries after middleware runs', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);

    tenancy()->initialize($company);

    actingAs($user)
        ->get('/_test/ensure-tenancy-ended')
        ->assertOk();
});
