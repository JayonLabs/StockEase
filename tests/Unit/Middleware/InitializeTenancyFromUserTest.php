<?php

use App\Http\Middleware\InitializeTenancyFromUser;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }

    Route::get('/_test/tenancy', function () {
        return ['tenant_initialized' => tenancy()->initialized, 'tenant_id' => tenant()?->getTenantKey()];
    })->middleware('web');
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('initializes tenancy when authenticated user has company_id', function () {
    $company = Company::create([
        'name' => 'Test Co', 'slug' => 'test-co-'.uniqid(), 'is_active' => true,
    ]);
    /** @var User $user */
    $user = User::factory()->create(['company_id' => $company->id]);

    actingAs($user)
        ->get('/_test/tenancy')
        ->assertJson([
            'tenant_initialized' => true,
            'tenant_id' => $company->id,
        ]);
});

it('does not initialize tenancy when user has no company_id (platform admin)', function () {
    /** @var User $user */
    $user = User::factory()->create(['company_id' => null]);

    actingAs($user)
        ->get('/_test/tenancy')
        ->assertJson([
            'tenant_initialized' => false,
            'tenant_id' => null,
        ]);
});

it('does not initialize tenancy for unauthenticated requests', function () {
    get('/_test/tenancy')
        ->assertJson([
            'tenant_initialized' => false,
            'tenant_id' => null,
        ]);
});

it('initializes correct tenant for different users', function () {
    $companyA = Company::create([
        'name' => 'Company A', 'slug' => 'company-a-'.uniqid(), 'is_active' => true,
    ]);
    $companyB = Company::create([
        'name' => 'Company B', 'slug' => 'company-b-'.uniqid(), 'is_active' => true,
    ]);
    /** @var User $userA */
    $userA = User::factory()->create(['company_id' => $companyA->id]);
    /** @var User $userB */
    $userB = User::factory()->create(['company_id' => $companyB->id]);

    actingAs($userA)
        ->get('/_test/tenancy')
        ->assertJson(['tenant_id' => $companyA->id]);

    actingAs($userB)
        ->get('/_test/tenancy')
        ->assertJson(['tenant_id' => $companyB->id]);
});

it('handles middleware directly via handle method', function () {
    $company = Company::create([
        'name' => 'Direct Test', 'slug' => 'direct-test-'.uniqid(), 'is_active' => true,
    ]);
    /** @var User $user */
    $user = User::factory()->create(['company_id' => $company->id]);

    $request = Request::create('/test', 'GET');
    $request->setUserResolver(fn () => $user);

    $middleware = new InitializeTenancyFromUser;
    $response = $middleware->handle($request, fn ($req) => new Response('OK'));

    expect($response->getContent())->toBe('OK');
    expect(tenancy()->initialized)->toBeTrue();
    expect(tenant()->getTenantKey())->toBe($company->id);
});
