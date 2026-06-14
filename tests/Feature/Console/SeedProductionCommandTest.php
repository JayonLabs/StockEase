<?php

use App\Console\Commands\SeedProduction;
use App\Enums\Role as RoleEnum;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\artisan;

uses(LazilyRefreshDatabase::class);

describe('SeedProduction Command', function () {

    // -----------------------------------------------------------------------
    // Permissions
    // -----------------------------------------------------------------------

    it('creates all permissions when run on empty database', function () {
        artisan('stockease:seed-production --force')
            ->assertSuccessful();

        expect(Permission::all())->not->toBeEmpty()
            ->and(Permission::count())->toBeGreaterThan(80);
    });

    it('does not register view_queue_worker_logs (moved to role-based access)', function () {
        artisan('stockease:seed-production --force')
            ->assertSuccessful();

        expect(Permission::where('name', 'view_queue_worker_logs')->exists())->toBeFalse();
    });

    it('view_activity_logs permission exists after seed', function () {
        artisan('stockease:seed-production --force')
            ->assertSuccessful();

        expect(Permission::where('name', 'view_activity_logs')->where('guard_name', 'web')->exists())
            ->toBeTrue();
    });

    it('view_activity_logs is NOT assigned to any role', function () {
        artisan('stockease:seed-production --force')
            ->assertSuccessful();

        foreach (Role::all() as $role) {
            expect($role->hasPermissionTo('view_activity_logs'))
                ->toBeFalse("Role '{$role->name}' should NOT have view_activity_logs");
        }
    });

    it('does not duplicate permissions when run multiple times', function () {
        artisan('stockease:seed-production --force')->assertSuccessful();
        $firstCount = Permission::count();

        artisan('stockease:seed-production --force')->assertSuccessful();

        expect(Permission::count())->toBe($firstCount);
    });

    // -----------------------------------------------------------------------
    // Roles
    // -----------------------------------------------------------------------

    it('creates all five roles', function () {
        artisan('stockease:seed-production --force')
            ->assertSuccessful();

        expect(Role::all()->pluck('name')->toArray())
            ->toEqualCanonicalizing(['super_admin', 'platform_owner', 'admin', 'cashier', 'warehouse']);
    });

    it('super_admin role has no explicit permissions (handled by Gate::before)', function () {
        artisan('stockease:seed-production --force')
            ->assertSuccessful();

        expect(Role::findByName('super_admin', 'web')->permissions)->toBeEmpty();
    });

    it('platform_owner role has no explicit permissions (access via role middleware)', function () {
        artisan('stockease:seed-production --force')
            ->assertSuccessful();

        expect(Role::findByName('platform_owner', 'web')->permissions)->toBeEmpty();
    });

    it('assigns permissions to the admin role', function () {
        artisan('stockease:seed-production --force')
            ->assertSuccessful();

        $adminRole = Role::findByName('admin', 'web');

        expect($adminRole->permissions)->not->toBeEmpty()
            ->and($adminRole->hasPermissionTo('view_users'))->toBeTrue()
            ->and($adminRole->hasPermissionTo('view_products'))->toBeTrue()
            ->and($adminRole->hasPermissionTo('access_pos'))->toBeTrue();
    });

    it('assigns permissions to the cashier role', function () {
        artisan('stockease:seed-production --force')
            ->assertSuccessful();

        $cashierRole = Role::findByName('cashier', 'web');

        expect($cashierRole->permissions)->not->toBeEmpty()
            ->and($cashierRole->hasPermissionTo('access_pos'))->toBeTrue()
            ->and($cashierRole->hasPermissionTo('checkout_pos'))->toBeTrue();
    });

    it('assigns permissions to the warehouse role', function () {
        artisan('stockease:seed-production --force')
            ->assertSuccessful();

        $warehouseRole = Role::findByName('warehouse', 'web');

        expect($warehouseRole->permissions)->not->toBeEmpty()
            ->and($warehouseRole->hasPermissionTo('view_products'))->toBeTrue()
            ->and($warehouseRole->hasPermissionTo('create_products'))->toBeTrue();
    });

    it('does not duplicate roles when run multiple times', function () {
        artisan('stockease:seed-production --force')->assertSuccessful();
        $firstCount = Role::count();

        artisan('stockease:seed-production --force')->assertSuccessful();

        expect(Role::count())->toBe($firstCount);
    });

    // -----------------------------------------------------------------------
    // Admin (tenant super_admin) user
    // -----------------------------------------------------------------------

    it('creates the tenant admin user', function () {
        artisan('stockease:seed-production --force')
            ->assertSuccessful();

        $user = User::where('email', UserSeeder::DEMO_EMAIL)->first();

        expect($user)->not->toBeNull()
            ->and($user->name)->toBe('Dewa Jayon');
    });

    it('assigns the super_admin role to the tenant admin user', function () {
        artisan('stockease:seed-production --force')
            ->assertSuccessful();

        $user = User::where('email', UserSeeder::DEMO_EMAIL)->first();

        expect($user->hasRole(RoleEnum::SuperAdmin->value))->toBeTrue();
    });

    it('grants all permissions directly to the tenant admin user', function () {
        artisan('stockease:seed-production --force')
            ->assertSuccessful();

        $user = User::where('email', UserSeeder::DEMO_EMAIL)->first();

        expect($user->permissions)->toHaveCount(Permission::count())
            ->and($user->can('view_activity_logs'))->toBeTrue()
            ->and($user->can('view_dashboard'))->toBeTrue();
    });

    it('does not duplicate the admin user when run multiple times', function () {
        artisan('stockease:seed-production --force')->assertSuccessful();
        $firstCount = User::count();

        artisan('stockease:seed-production --force')->assertSuccessful();

        expect(User::count())->toBe($firstCount)
            ->and(User::where('email', UserSeeder::DEMO_EMAIL)->count())->toBe(1);
    });

    it('re-syncs all permissions to the admin user on subsequent runs', function () {
        artisan('stockease:seed-production --force')->assertSuccessful();

        $user = User::where('email', UserSeeder::DEMO_EMAIL)->first();
        $perm = $user->permissions->first();
        $user->revokePermissionTo($perm);

        expect($user->fresh()->permissions->count())->toBe(Permission::count() - 1);

        artisan('stockease:seed-production --force')->assertSuccessful();

        expect($user->fresh()->permissions)->toHaveCount(Permission::count());
    });

    // -----------------------------------------------------------------------
    // Platform owner user
    // -----------------------------------------------------------------------

    it('creates the platform owner user', function () {
        artisan('stockease:seed-production --force')
            ->assertSuccessful();

        $owner = User::where('email', SeedProduction::PLATFORM_OWNER_EMAIL)->first();

        expect($owner)->not->toBeNull()
            ->and($owner->name)->toBe('Dewa Jayon');
    });

    it('assigns platform_owner and super_admin roles to the platform owner', function () {
        artisan('stockease:seed-production --force')
            ->assertSuccessful();

        $owner = User::where('email', SeedProduction::PLATFORM_OWNER_EMAIL)->first();

        expect($owner->hasRole(RoleEnum::PlatformOwner->value))->toBeTrue()
            ->and($owner->hasRole(RoleEnum::SuperAdmin->value))->toBeTrue();
    });

    it('creates a company and associates it with the platform owner', function () {
        artisan('stockease:seed-production --force')
            ->assertSuccessful();

        $owner = User::where('email', SeedProduction::PLATFORM_OWNER_EMAIL)->first();
        $company = Company::where('slug', 'stockease-platform')->first();

        expect($company)->not->toBeNull()
            ->and($owner->company_id)->toBe($company->id);
    });

    it('does not duplicate the platform owner when run multiple times', function () {
        artisan('stockease:seed-production --force')->assertSuccessful();
        $firstCount = User::count();

        artisan('stockease:seed-production --force')->assertSuccessful();

        expect(User::count())->toBe($firstCount)
            ->and(User::where('email', SeedProduction::PLATFORM_OWNER_EMAIL)->count())->toBe(1);
    });

    it('does not duplicate the platform company when run multiple times', function () {
        artisan('stockease:seed-production --force')->assertSuccessful();

        artisan('stockease:seed-production --force')->assertSuccessful();

        expect(Company::where('slug', 'stockease-platform')->count())->toBe(1);
    });

    it('platform owner cannot access tenant routes (role middleware enforces separation)', function () {
        artisan('stockease:seed-production --force')
            ->assertSuccessful();

        $owner = User::where('email', SeedProduction::PLATFORM_OWNER_EMAIL)->first();

        expect($owner->hasRole(RoleEnum::PlatformOwner->value))->toBeTrue()
            ->and($owner->hasRole('admin'))->toBeFalse()
            ->and($owner->hasRole('cashier'))->toBeFalse()
            ->and($owner->hasRole('warehouse'))->toBeFalse();
    });
});
