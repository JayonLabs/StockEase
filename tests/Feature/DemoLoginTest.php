<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\artisan;
use function Pest\Laravel\assertAuthenticated;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\seed;

uses(LazilyRefreshDatabase::class);

describe('Demo Login — UserSeeder', function () {
    beforeEach(function () {
        seed(RoleAndPermissionSeeder::class);
    });

    it('creates the demo user with correct attributes', function () {
        seed(UserSeeder::class);

        $user = User::where('email', UserSeeder::DEMO_EMAIL)->first();

        expect($user)->not->toBeNull();
        expect($user->name)->toBe('Dewa Jayon');
        expect($user->hasRole(Role::SuperAdmin->value))->toBeTrue();
    });

    it('grants all permissions to the demo user', function () {
        seed(UserSeeder::class);

        $user = User::where('email', UserSeeder::DEMO_EMAIL)->first();

        expect($user->permissions)->toHaveCount(Permission::count());
        expect($user->can('view_dashboard'))->toBeTrue();
        expect($user->can('view_activity_logs'))->toBeTrue();
        expect($user->can('view_queue_worker_logs'))->toBeTrue();
        expect($user->can('create_users'))->toBeTrue();
    });

    it('is idempotent — creates exactly one demo user on multiple runs', function () {
        seed(UserSeeder::class);
        seed(UserSeeder::class);
        seed(UserSeeder::class);

        expect(User::where('email', UserSeeder::DEMO_EMAIL)->count())->toBe(1);
    });

    it('does not overwrite existing demo user data on re-run', function () {
        seed(UserSeeder::class);

        $user = User::where('email', UserSeeder::DEMO_EMAIL)->first();
        $user->update(['name' => 'Changed Name']);

        seed(UserSeeder::class);

        expect($user->fresh()->name)->toBe('Changed Name');
    });

    it('re-syncs permissions for existing demo user', function () {
        seed(UserSeeder::class);

        $user = User::where('email', UserSeeder::DEMO_EMAIL)->first();

        // Remove a permission to verify re-sync
        $perm = $user->permissions->first();
        $user->revokePermissionTo($perm);

        expect($user->fresh()->permissions->count())->toBe(Permission::count() - 1);

        seed(UserSeeder::class);

        expect($user->fresh()->permissions)->toHaveCount(Permission::count());
    });
});

describe('Demo Login — SeedProduction Command', function () {
    it('creates the demo user via production command', function () {
        artisan('stockease:seed-production --force')
            ->assertSuccessful();

        $user = User::where('email', UserSeeder::DEMO_EMAIL)->first();

        expect($user)->not->toBeNull();
        expect($user->hasRole(Role::SuperAdmin->value))->toBeTrue();
        expect($user->permissions)->toHaveCount(Permission::count());
    });

    it('does not duplicate demo user on multiple production seed runs', function () {
        artisan('stockease:seed-production --force')->assertSuccessful();
        artisan('stockease:seed-production --force')->assertSuccessful();
        artisan('stockease:seed-production --force')->assertSuccessful();

        expect(User::where('email', UserSeeder::DEMO_EMAIL)->count())->toBe(1);
    });
});

describe('Demo Login — Authentication', function () {
    beforeEach(function () {
        seed(RoleAndPermissionSeeder::class);
    });

    it('can authenticate with demo credentials', function () {
        User::factory()->create([
            'name' => 'Dewa Jayon',
            'email' => UserSeeder::DEMO_EMAIL,
            'password' => UserSeeder::DEMO_PASSWORD,
            'role' => Role::SuperAdmin->value,
        ]);

        $response = post('/login', [
            'email' => UserSeeder::DEMO_EMAIL,
            'password' => UserSeeder::DEMO_PASSWORD,
        ]);

        assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    });

    it('rejects login with wrong password for demo user', function () {
        User::factory()->create([
            'name' => 'Dewa Jayon',
            'email' => UserSeeder::DEMO_EMAIL,
            'password' => UserSeeder::DEMO_PASSWORD,
            'role' => Role::SuperAdmin->value,
        ]);

        post('/login', [
            'email' => UserSeeder::DEMO_EMAIL,
            'password' => 'wrong-password',
        ]);

        assertGuest();
    });

    it('rejects login with wrong email for demo user', function () {
        post('/login', [
            'email' => 'wrong@email.com',
            'password' => UserSeeder::DEMO_PASSWORD,
        ]);

        assertGuest();
    });

    it('rejects login with empty fields', function () {
        post('/login', [
            'email' => '',
            'password' => '',
        ])->assertInvalid(['email', 'password']);

        assertGuest();
    });

    it('login page renders successfully', function () {
        $response = get('/login');

        $response->assertSuccessful();
    });
});
