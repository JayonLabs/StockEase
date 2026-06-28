<?php

use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticated;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Plan::insert([
        [
            'name' => 'Pemula',
            'slug' => 'pemula',
            'price_monthly' => 50000,
            'price_annual' => 500000,
            'max_products' => 100,
            'max_users' => 3,
            'max_warehouses' => 1,
            'trial_days' => 14,
            'sort_order' => 1,
        ],
        [
            'name' => 'Profesional',
            'slug' => 'profesional',
            'price_monthly' => 149000,
            'price_annual' => 1490000,
            'max_products' => 1000,
            'max_users' => 10,
            'max_warehouses' => 3,
            'trial_days' => 0,
            'sort_order' => 2,
        ],
    ]);
});

it('renders the registration page', function () {
    get(route('register'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('Auth/Register'));
});

it('registers a new user with company and subscription', function () {
    $response = post(route('register'), [
        'name' => 'Budi Santoso',
        'email' => 'budi@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'company_name' => 'Toko Makmur Jaya',
    ]);

    $response->assertRedirect(route('dashboard'));

    assertDatabaseHas('companies', ['name' => 'Toko Makmur Jaya']);
    assertDatabaseHas('users', ['name' => 'Budi Santoso', 'email' => 'budi@example.com']);
    assertDatabaseHas('subscriptions', ['status' => 'trialing']);

    $user = User::where('email', 'budi@example.com')->first();
    expect($user->company_id)->not->toBeNull();
    expect($user->hasRole('super_admin'))->toBeTrue();

    $company = Company::where('name', 'Toko Makmur Jaya')->first();
    expect($company->owner_id)->toBe($user->id);
    expect($company->activeSubscription())->not->toBeNull();
    expect($company->activeSubscription()->plan->slug)->toBe('pemula');
});

it('automatically authenticates after registration', function () {
    post(route('register'), [
        'name' => 'Ani',
        'email' => 'ani@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'company_name' => 'Toko Ani',
    ]);

    assertAuthenticated();
});

it('validates required fields', function () {
    post(route('register'), [])
        ->assertSessionHasErrors(['name', 'email', 'password', 'company_name']);
});

it('validates email uniqueness', function () {
    User::factory()->create(['email' => 'exists@example.com']);

    post(route('register'), [
        'name' => 'Test',
        'email' => 'exists@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'company_name' => 'Test Co',
    ])->assertSessionHasErrors('email');
});

it('validates password confirmation', function () {
    post(route('register'), [
        'name' => 'Test',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'different',
        'company_name' => 'Test Co',
    ])->assertSessionHasErrors('password');
});

it('validates password minimum length', function () {
    post(route('register'), [
        'name' => 'Test',
        'email' => 'test@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
        'company_name' => 'Test Co',
    ])->assertSessionHasErrors('password');
});

it('redirects authenticated users away from register', function () {
    $user = User::factory()->create();

    /** @var User $user */
    $response = actingAs($user)->get(route('register'));

    $response->assertRedirect(route('dashboard'));
});

it('creates company with unique slug', function () {
    post(route('register'), [
        'name' => 'Budi',
        'email' => 'budi2@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'company_name' => 'Toko Makmur',
    ]);

    $company = Company::where('name', 'Toko Makmur')->first();
    expect($company->slug)->toContain('toko-makmur');
    expect(strlen($company->slug))->toBeGreaterThan(11);
});

it('registration creates Pemula subscription with 14-day trial', function () {
    post(route('register'), [
        'name' => 'Trial User',
        'email' => 'trial@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'company_name' => 'Trial Company',
    ])->assertRedirect(route('dashboard'));

    $company = Company::where('name', 'Trial Company')->first();
    $subscription = $company->subscription;

    expect($subscription)->not->toBeNull();
    expect($subscription->plan->slug)->toBe('pemula');
    expect($subscription->status)->toBe('trialing');
    expect($subscription->trial_ends_at)->not->toBeNull();
    expect($subscription->trial_ends_at->isFuture())->toBeTrue();

    $expectedTrialEnd = now()->addDays(14);
    expect($subscription->trial_ends_at->toDateString())->toBe($expectedTrialEnd->toDateString());
});

it('marks company as having used trial after registration so double trial is prevented', function () {
    post(route('register'), [
        'name' => 'Cegah Trial',
        'email' => 'cegah@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'company_name' => 'No Double Trial Co',
    ])->assertRedirect(route('dashboard'));

    $company = Company::where('name', 'No Double Trial Co')->first();

    expect($company->hadTrial())->toBeTrue();
});
