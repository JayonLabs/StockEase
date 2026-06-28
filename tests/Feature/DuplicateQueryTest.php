<?php

use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

use function Pest\Laravel\actingAs;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    /** @var TestCase&object{company: Company, user: User} $this */
    // Pemula diperlukan sebagai fallback assignFreeSubscription
    Plan::factory()->pemula()->create();
    $enterprise = Plan::factory()->enterprise()->create();

    $this->company = Company::create([
        'name' => 'Toko Test',
        'slug' => 'toko-test-'.uniqid(),
        'is_active' => true,
    ]);
    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->company->update(['owner_id' => $this->user->id]);

    // Enterprise agar semua fitur (file_manager, dsb.) dapat diakses dalam test ini
    $this->company->subscription()->create([
        'plan_id' => $enterprise->id,
        'status' => 'active',
        'starts_at' => now(),
    ]);
});

it('does not duplicate the company query between middlewares', function () {
    /** @var TestCase&object{company: Company, user: User} $this */
    actingAs($this->user);

    DB::enableQueryLog();

    $this->get(route('file-manager.index'));

    $queries = DB::getQueryLog();

    $companyQueries = collect($queries)
        ->filter(fn ($q) => str_contains($q['query'], 'select * from `companies`'));

    expect($companyQueries)->toHaveCount(1);
});

it('still initializes tenancy correctly', function () {
    /** @var TestCase&object{company: Company, user: User} $this */
    actingAs($this->user);

    $this->get(route('file-manager.index'))->assertSuccessful();

    expect(tenancy()->initialized)->toBeTrue();
    expect(tenancy()->tenant->id)->toBe($this->company->id);
});

it('still shares subscription data via inertia', function () {
    /** @var TestCase&object{company: Company, user: User} $this */
    actingAs($this->user);

    $this->get(route('file-manager.index'))
        ->assertInertia(
            fn ($page) => $page
                ->has('auth.subscription.plan')
                ->where('auth.subscription.plan.slug', 'enterprise')
        );
});

it('does not duplicate company query on authenticated pages', function (string $route) {
    /** @var TestCase&object{company: Company, user: User} $this */
    actingAs($this->user);

    DB::enableQueryLog();

    $this->get($route)->assertSuccessful();

    $queries = DB::getQueryLog();

    $companyQueries = collect($queries)
        ->filter(fn ($q) => str_contains($q['query'], 'select * from `companies`'));

    expect($companyQueries)->toHaveCount(1);
})->with([
    'file-manager' => fn () => route('file-manager.index'),
    'dashboard' => fn () => route('dashboard'),
    'profile' => fn () => route('profile.edit'),
]);
