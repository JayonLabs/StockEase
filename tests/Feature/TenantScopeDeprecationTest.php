<?php

use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use App\Tenancy\TenantScope;

use function Pest\Laravel\actingAs;

// ============================================================
// 1. Deprecation: Trait static property access via class
// ============================================================

test('accessing $tenantIdColumn via model class does not trigger deprecation', function () {
    $caught = null;

    set_error_handler(function (int $severity, string $message) use (&$caught) {
        if ($severity === E_DEPRECATED && str_contains($message, 'BelongsToTenant::$tenantIdColumn')) {
            $caught = $message;
        }

        return false;
    });

    $value = Category::$tenantIdColumn;

    restore_error_handler();

    expect($caught)->toBeNull('Accessing Category::$tenantIdColumn should not trigger deprecation');
    expect($value)->toBe('company_id');
});

test('accessing $tenantIdColumn on other model classes does not trigger deprecation', function (string $modelClass) {
    $caught = null;

    set_error_handler(function (int $severity, string $message) use (&$caught) {
        if ($severity === E_DEPRECATED && str_contains($message, 'BelongsToTenant::$tenantIdColumn')) {
            $caught = $message;
        }

        return false;
    });

    $value = $modelClass::$tenantIdColumn;

    restore_error_handler();

    expect($caught)->toBeNull("Accessing {$modelClass}::\$tenantIdColumn should not trigger deprecation");
    expect($value)->toBe('company_id');
})->with([
    Product::class,
]);

// ============================================================
// 2. Config value is correct
// ============================================================

test('config tenancy.tenant_key_column returns company_id', function () {
    expect(config('tenancy.tenant_key_column'))->toBe('company_id');
});

// ============================================================
// 3. TenantScope reads column from config
// ============================================================

test('TenantScope apply method qualifies company_id column when tenancy is initialized', function () {
    $company = Company::create(['name' => 'Test', 'slug' => 'scope-test']);
    $user = User::factory()->create(['company_id' => $company->id]);

    tenancy()->initialize($company);

    $scope = new TenantScope;
    $builder = Product::query();
    $model = new Product;

    $reflection = new ReflectionMethod($scope, 'apply');
    $reflection->invoke($scope, $builder, $model);

    $wheres = $builder->getQuery()->wheres;

    expect($wheres)->not->toBeEmpty();

    $tenantWhere = collect($wheres)->first(fn ($where) => str_contains($where['column'] ?? '', 'company_id'));

    expect($tenantWhere)->not->toBeNull('TenantScope should add WHERE clause on company_id column');
    expect($tenantWhere['column'] ?? '')->toEndWith('.company_id');

    tenancy()->end();
});

// ============================================================
// 4. Model creating hook auto-fills company_id (integration)
// ============================================================

test('models auto-fill company_id when tenancy is initialized', function () {
    $company = Company::create(['name' => 'Test', 'slug' => 'test-co']);
    $user = User::factory()->create(['company_id' => $company->id]);

    tenancy()->initialize($company);

    $category = Category::create(['name' => 'Test Category', 'slug' => 'test-category']);

    expect($category->company_id)->toBe($company->id);

    tenancy()->end();
});

// ============================================================
// 5. tenant() relationship uses company_id
// ============================================================

test('tenant() relationship resolves to the correct company', function () {
    $company = Company::create(['name' => 'Test', 'slug' => 'test-co-2']);
    $user = User::factory()->create(['company_id' => $company->id]);

    tenancy()->initialize($company);

    $category = Category::create(['name' => 'Test', 'slug' => 'test-2']);

    $tenant = $category->tenant;

    expect($tenant)->toBeInstanceOf(Company::class);
    expect($tenant->id)->toBe($company->id);

    tenancy()->end();
});

// ============================================================
// 6. withoutTenancy() bypasses the tenant scope
// ============================================================

test('withoutTenancy() bypasses tenant scope', function () {
    $company = Company::create(['name' => 'Test', 'slug' => 'test-co-3']);
    $user = User::factory()->create(['company_id' => $company->id]);

    tenancy()->initialize($company);

    Category::create(['name' => 'Scoped', 'slug' => 'scoped']);

    $scoped = Category::count();
    $unscoped = Category::withoutTenancy()->count();

    expect($scoped)->toBe($unscoped);

    tenancy()->end();
});

// ============================================================
// 7. Data isolation between tenants
// ============================================================

test('data is properly isolated by company_id', function () {
    $companyA = Company::create(['name' => 'A', 'slug' => 'co-a']);
    $userA = User::factory()->create(['company_id' => $companyA->id]);

    $companyB = Company::create(['name' => 'B', 'slug' => 'co-b']);
    $userB = User::factory()->create(['company_id' => $companyB->id]);

    tenancy()->initialize($companyA);
    Category::create(['name' => 'Category A', 'slug' => 'cat-a']);
    tenancy()->end();

    tenancy()->initialize($companyB);
    Category::create(['name' => 'Category B', 'slug' => 'cat-b']);
    tenancy()->end();

    tenancy()->initialize($companyA);

    /** @var User $userA */
    actingAs($userA);

    expect(Category::where('slug', 'cat-a')->exists())->toBeTrue();
    expect(Category::where('slug', 'cat-b')->exists())->toBeFalse();

    tenancy()->end();
});
