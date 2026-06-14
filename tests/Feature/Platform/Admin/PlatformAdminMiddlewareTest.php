<?php

use App\Models\Company;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    seed(RoleAndPermissionSeeder::class);

    Route::middleware(['web', 'auth', 'role:platform_owner', 'ensure.tenancy.ended'])
        ->get('/_test/platform-only', function () {
            return ['tenant_initialized' => tenancy()->initialized];
        })
        ->name('_test.platform-only');
});

it('requires authentication', function () {
    $this->get('/_test/platform-only')
        ->assertRedirect(route('login'));
});

it('requires platform_owner role', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->syncRoles('admin');

    actingAs($user)
        ->get('/_test/platform-only')
        ->assertForbidden();
});

it('denies super_admin (tenant owner) role', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->syncRoles('super_admin');

    actingAs($user)
        ->get('/_test/platform-only')
        ->assertForbidden();
});

it('allows platform_owner user with demo company', function () {
    $user = createPlatformOwner();

    actingAs($user)
        ->get('/_test/platform-only')
        ->assertOk();
});

it('ensures tenancy ends for platform_owner requests', function () {
    $user = createPlatformOwner();
    $demoCompany = Company::find($user->company_id);

    tenancy()->initialize($demoCompany);

    actingAs($user)
        ->get('/_test/platform-only')
        ->assertOk()
        ->assertJson(['tenant_initialized' => false]);
});

it('returns 403 for warehouse role', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->syncRoles('warehouse');

    actingAs($user)
        ->get('/_test/platform-only')
        ->assertForbidden();
});

it('returns 403 for cashier role', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->syncRoles('cashier');

    actingAs($user)
        ->get('/_test/platform-only')
        ->assertForbidden();
});
