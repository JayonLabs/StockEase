<?php

use App\Models\Plan;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    seed(RoleAndPermissionSeeder::class);

    Plan::create([
        'name' => 'Pemula', 'slug' => 'pemula',
        'price_monthly' => 0, 'price_annual' => 0,
        'max_products' => 100, 'max_users' => 3, 'max_warehouses' => 1,
        'sort_order' => 1,
    ]);

    Plan::create([
        'name' => 'Profesional', 'slug' => 'profesional',
        'price_monthly' => 299000, 'price_annual' => 249000,
        'max_products' => 1000, 'max_users' => 10, 'max_warehouses' => 3,
        'sort_order' => 2,
    ]);
});

it('denies access for non-admin users', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create();
    $cashier->syncRoles('cashier');

    actingAs($cashier)
        ->get(route('plans.index'))
        ->assertForbidden();
});

it('lists all plans in order', function () {
    /** @var User $admin */
    $admin = User::factory()->create();
    $admin->syncRoles('admin');

    Plan::create([
        'name' => 'Enterprise', 'slug' => 'enterprise',
        'price_monthly' => 999000, 'price_annual' => 849000,
        'sort_order' => 3,
    ]);

    actingAs($admin)
        ->get(route('plans.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('plans', 3)
            ->where('plans.0.slug', 'pemula')
        );
});

it('can update plan price and limits', function () {
    /** @var User $admin */
    $admin = User::factory()->create();
    $admin->syncRoles('admin');

    $plan = Plan::where('slug', 'profesional')->first();

    actingAs($admin)
        ->patch(route('plans.update', $plan), [
            'price_monthly' => 399000,
            'max_products' => 2000,
        ])
        ->assertRedirect();

    expect($plan->fresh()->price_monthly)->toBe('399000.00');
    expect($plan->fresh()->max_products)->toBe(2000);
});
