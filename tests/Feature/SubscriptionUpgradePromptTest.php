<?php

use App\Models\Category;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Unit;
use App\Models\User;
use App\Services\Subscription\PlanLimitService;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

uses(LazilyRefreshDatabase::class);

// ---------------------------------------------------------------------------
// User creation — upgrade prompt when limit is reached
// ---------------------------------------------------------------------------

beforeEach(function () {
    /** @var object{plan: Plan, company: Company, user: User} $this */
    $this->plan = Plan::factory()->create([
        'slug' => 'test-plan',
        'max_products' => 100,
        'max_users' => 2,
        'max_warehouses' => 2,
    ]);

    $this->company = Company::factory()->create();
    $this->user = User::factory()->create([
        'company_id' => $this->company->id,
    ]);
    $this->user->syncRoles('admin');
    $this->company->update(['owner_id' => $this->user->id]);

    $this->company->subscription()->create([
        'plan_id' => $this->plan->id,
        'status' => 'active',
        'starts_at' => now(),
    ]);
});

it('creates user successfully when below limit', function () {
    /** @var object{user: User, company: Company, plan: Plan} $this */
    actingAs($this->user)
        ->post(route('users.store'), [
            'name' => 'New Staff',
            'email' => 'staff@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'role' => 'cashier',
        ])
        ->assertSessionHas('success', 'User berhasil ditambahkan');

    assertDatabaseHas('users', ['email' => 'staff@example.com']);
});

it('blocks user creation and returns error flash when limit exceeded', function () {
    /** @var object{user: User, company: Company, plan: Plan} $this */
    $this->plan->update(['max_users' => 1]);

    actingAs($this->user)
        ->post(route('users.store'), [
            'name' => 'Second User',
            'email' => 'second@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'role' => 'cashier',
        ])
        ->assertSessionHas('error', 'Batas maksimal user untuk plan '.$this->plan->name.' telah tercapai. Upgrade plan Anda.');

    assertDatabaseCount('users', 1);
});

it('returns error even for super_admin when user limit is reached', function () {
    /** @var object{user: User, company: Company, plan: Plan} $this */
    $this->user->syncRoles('super_admin');
    $this->plan->update(['max_users' => 1]);

    actingAs($this->user)
        ->post(route('users.store'), [
            'name' => 'Over Limit',
            'email' => 'over@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'role' => 'cashier',
        ])
        ->assertSessionHas('error');
});

it('allows user creation when max_users is null (unlimited)', function () {
    /** @var object{user: User, company: Company, plan: Plan} $this */
    $this->plan->update(['max_users' => null]);

    actingAs($this->user)
        ->post(route('users.store'), [
            'name' => 'Unlimited User',
            'email' => 'unlimited@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'role' => 'cashier',
        ])
        ->assertSessionHas('success');
});

// ---------------------------------------------------------------------------
// PlanLimitService — edge cases
// ---------------------------------------------------------------------------

describe('PlanLimitService edge cases', function () {
    beforeEach(function () {
        /** @var object{service: PlanLimitService, subService: SubscriptionService, owner: User, company: Company} $this */
        $this->service = new PlanLimitService;
        $this->subService = new SubscriptionService;

        $this->owner = User::factory()->create();
        $this->company = Company::factory()->create(['owner_id' => $this->owner->id]);
        $this->owner->update(['company_id' => $this->company->id]);
    });

    it('unlimited plan allows any number of users', function () {
        /** @var object{service: PlanLimitService, subService: SubscriptionService, owner: User, company: Company} $this */
        $enterprise = Plan::factory()->create([
            'slug' => 'enterprise',
            'max_users' => null,
        ]);
        $this->subService->createTrial($this->company, $enterprise);

        User::factory()->count(100)->create(['company_id' => $this->company->id]);

        expect($this->service->canAddUser($this->company))->toBeTrue();
    });

    it('unlimited plan allows any number of products', function () {
        /** @var object{service: PlanLimitService, subService: SubscriptionService, owner: User, company: Company} $this */
        $enterprise = Plan::factory()->create([
            'slug' => 'enterprise',
            'max_products' => null,
        ]);
        $this->subService->createTrial($this->company, $enterprise);

        expect($this->service->canAddProduct($this->company))->toBeTrue();
    });

    it('unlimited plan allows any number of warehouses', function () {
        /** @var object{service: PlanLimitService, subService: SubscriptionService, owner: User, company: Company} $this */
        $enterprise = Plan::factory()->create([
            'slug' => 'enterprise',
            'max_warehouses' => null,
        ]);
        $this->subService->createTrial($this->company, $enterprise);

        expect($this->service->canAddWarehouse($this->company))->toBeTrue();
    });

    it('returns true when company has no active subscription (no plan = no limit)', function () {
        /** @var object{service: PlanLimitService, subService: SubscriptionService, owner: User, company: Company} $this */
        expect($this->service->canAddUser($this->company))->toBeTrue();
        expect($this->service->canAddProduct($this->company))->toBeTrue();
        expect($this->service->canAddWarehouse($this->company))->toBeTrue();
    });

    it('returns true when company is null (no plan = no limit)', function () {
        /** @var object{service: PlanLimitService, subService: SubscriptionService, owner: User, company: Company} $this */
        expect($this->service->canAddUser(null))->toBeTrue();
        expect($this->service->canAddProduct(null))->toBeTrue();
        expect($this->service->canAddWarehouse(null))->toBeTrue();
    });
});

// ---------------------------------------------------------------------------
// UpgradePromptDialog presence in Inertia page props
// ---------------------------------------------------------------------------

describe('upgrade prompt flash messages', function () {
    it('sets error flash with resource-specific message for users', function () {
        /** @var object{user: User, company: Company, plan: Plan} $this */
        $this->plan->update(['max_users' => 1]);

        actingAs($this->user)
            ->post(route('users.store'), [
                'name' => 'Flash Test',
                'email' => 'flash@example.com',
                'password' => 'Password1!',
                'password_confirmation' => 'Password1!',
                'role' => 'cashier',
            ])
            ->assertSessionHas('error')
            ->assertSessionHas('error', fn ($msg) => str_contains($msg, 'Batas maksimal user'));
    });

    it('passes error flash through Inertia response', function () {
        /** @var object{user: User, company: Company, plan: Plan} $this */
        $this->plan->update(['max_users' => 1]);

        actingAs($this->user)
            ->post(route('users.store'), [
                'name' => 'Inertia User',
                'email' => 'inertia@example.com',
                'password' => 'Password1!',
                'password_confirmation' => 'Password1!',
                'role' => 'cashier',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    });
});

// ---------------------------------------------------------------------------
// Resource limit for products and warehouses also set error flash
// ---------------------------------------------------------------------------

describe('product limit errors', function () {
    it('blocks product creation with error flash when product limit exceeded', function () {
        /** @var object{user: User, company: Company, plan: Plan} $this */
        $this->user->syncRoles('admin');
        $category = Category::factory()->create();
        $unit = Unit::factory()->create();

        actingAs($this->user)
            ->post(route('products.store'), [
                'name' => 'Valid Product',
                'sku' => 'SKU-001',
                'barcode' => 'BARCODE-001',
                'category_id' => $category->id,
                'unit_id' => $unit->id,
                'purchase_price' => 1000,
                'selling_price' => 2000,
                'stock' => 10,
                'alert_stock' => 2,
            ])
            ->assertSessionHas('success');

        $this->plan->update(['max_products' => 1]);

        actingAs($this->user)
            ->post(route('products.store'), [
                'name' => 'Over Limit Product',
                'sku' => 'SKU-002',
                'barcode' => 'BARCODE-002',
                'category_id' => Category::factory()->create()->id,
                'unit_id' => Unit::factory()->create()->id,
                'purchase_price' => 1000,
                'selling_price' => 2000,
                'stock' => 10,
                'alert_stock' => 2,
            ])
            ->assertSessionHas('error', fn ($msg) => str_contains($msg, 'Batas maksimal produk'));
    });
});
