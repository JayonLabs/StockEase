<?php

namespace Tests\Feature\Middleware;

use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    /** @var object{company: Company, user: User, pemula: Plan, profesional: Plan, enterprise: Plan} $this */
    $this->pemula = Plan::factory()->pemula()->create();
    $this->profesional = Plan::factory()->profesional()->create();
    $this->enterprise = Plan::factory()->enterprise()->create();

    $this->company = Company::factory()->create();
    $this->user = User::factory()->create([
        'company_id' => $this->company->id,
        'role' => 'admin',
    ]);
    $this->company->update(['owner_id' => $this->user->id]);

    $this->company->subscription()->create([
        'plan_id' => $this->pemula->id,
        'status' => 'active',
        'starts_at' => now(),
    ]);
});

// ===========================================================================
// Platform Owner — melewati semua pengecekan fitur
// ===========================================================================

it('platform_owner bypasses all plan feature checks', function () {
    /** @var object{company: Company, user: User, pemula: Plan, profesional: Plan, enterprise: Plan} $this */
    /** @var User $platformOwner */
    $platformOwner = User::factory()->create(['company_id' => $this->company->id]);
    $platformOwner->syncRoles('platform_owner');

    actingAs($platformOwner)
        ->get(route('purchase.index'))
        ->assertOk();
});

// ===========================================================================
// Plan Pemula — fitur yang diblokir
// ===========================================================================

it('pemula user is blocked from purchasing page (feature: purchasing)', function () {
    /** @var object{company: Company, user: User, pemula: Plan, profesional: Plan, enterprise: Plan} $this */
    actingAs($this->user)
        ->get(route('purchase.index'))
        ->assertRedirect(route('subscription.index'));
});

it('pemula user can access shift management (feature: cashier_shift)', function () {
    /** @var object{company: Company, user: User, pemula: Plan, profesional: Plan, enterprise: Plan} $this */
    actingAs($this->user)
        ->get(route('shift.index'))
        ->assertOk();
});

it('pemula user is blocked from purchase report (feature: purchase_report)', function () {
    /** @var object{company: Company, user: User, pemula: Plan, profesional: Plan, enterprise: Plan} $this */
    actingAs($this->user)
        ->get(route('reports.purchase.index'))
        ->assertRedirect(route('subscription.index'));
});

it('pemula user is blocked from stock report (feature: stock_report)', function () {
    /** @var object{company: Company, user: User, pemula: Plan, profesional: Plan, enterprise: Plan} $this */
    actingAs($this->user)
        ->get(route('reports.stock.index'))
        ->assertRedirect(route('subscription.index'));
});

it('pemula user is blocked from profit loss report (feature: profit_loss)', function () {
    /** @var object{company: Company, user: User, pemula: Plan, profesional: Plan, enterprise: Plan} $this */
    actingAs($this->user)
        ->get(route('reports.profit-loss'))
        ->assertRedirect(route('subscription.index'));
});

it('pemula user is blocked from stock transfer (feature: multi_warehouse)', function () {
    /** @var object{company: Company, user: User, pemula: Plan, profesional: Plan, enterprise: Plan} $this */
    actingAs($this->user)
        ->get(route('stock-transfer.index'))
        ->assertRedirect(route('subscription.index'));
});

it('pemula user is blocked from file manager (feature: file_manager)', function () {
    /** @var object{company: Company, user: User, pemula: Plan, profesional: Plan, enterprise: Plan} $this */
    $this->user->givePermissionTo('view_file_manager');

    actingAs($this->user)
        ->get(route('file-manager.index'))
        ->assertRedirect(route('subscription.index'));
});

it('pemula user can access permissions page (feature: user_roles)', function () {
    /** @var object{company: Company, user: User, pemula: Plan, profesional: Plan, enterprise: Plan} $this */
    actingAs($this->user)
        ->get(route('permissions.index'))
        ->assertOk();
});

it('pemula user can access role permissions page (feature: user_roles)', function () {
    /** @var object{company: Company, user: User, pemula: Plan, profesional: Plan, enterprise: Plan} $this */
    actingAs($this->user)
        ->get(route('role-permissions.index'))
        ->assertOk();
});

it('pemula user can access user permissions page (feature: user_roles)', function () {
    /** @var object{company: Company, user: User, pemula: Plan, profesional: Plan, enterprise: Plan} $this */
    actingAs($this->user)
        ->get(route('user-permissions.index'))
        ->assertOk();
});

// ===========================================================================
// Plan Pemula — fitur yang masih bisa diakses
// ===========================================================================

it('pemula user can access sales report (feature: sales_report)', function () {
    /** @var object{company: Company, user: User, pemula: Plan, profesional: Plan, enterprise: Plan} $this */
    actingAs($this->user)
        ->get(route('reports.sale.index'))
        ->assertOk();
});

it('pemula user can access warehouse list', function () {
    /** @var object{company: Company, user: User, pemula: Plan, profesional: Plan, enterprise: Plan} $this */
    actingAs($this->user)
        ->get(route('warehouse.index'))
        ->assertOk();
});

// ===========================================================================
// Plan Profesional — fitur yang sudah bisa diakses
// ===========================================================================

it('profesional user can access purchasing', function () {
    /** @var object{company: Company, user: User, pemula: Plan, profesional: Plan, enterprise: Plan} $this */
    $this->company->subscription()->update(['plan_id' => $this->profesional->id]);

    actingAs($this->user)
        ->get(route('purchase.index'))
        ->assertOk();
});

it('profesional user can access shift management', function () {
    /** @var object{company: Company, user: User, pemula: Plan, profesional: Plan, enterprise: Plan} $this */
    $this->company->subscription()->update(['plan_id' => $this->profesional->id]);

    actingAs($this->user)
        ->get(route('shift.index'))
        ->assertOk();
});

it('profesional user can access purchase report', function () {
    /** @var object{company: Company, user: User, pemula: Plan, profesional: Plan, enterprise: Plan} $this */
    $this->company->subscription()->update(['plan_id' => $this->profesional->id]);

    actingAs($this->user)
        ->get(route('reports.purchase.index'))
        ->assertOk();
});

it('profesional user can access stock report', function () {
    /** @var object{company: Company, user: User, pemula: Plan, profesional: Plan, enterprise: Plan} $this */
    $this->company->subscription()->update(['plan_id' => $this->profesional->id]);

    actingAs($this->user)
        ->get(route('reports.stock.index'))
        ->assertOk();
});

it('profesional user can access stock transfer', function () {
    /** @var object{company: Company, user: User, pemula: Plan, profesional: Plan, enterprise: Plan} $this */
    $this->company->subscription()->update(['plan_id' => $this->profesional->id]);

    actingAs($this->user)
        ->get(route('stock-transfer.index'))
        ->assertOk();
});

// ===========================================================================
// Plan Profesional — fitur yang masih diblokir
// ===========================================================================

it('profesional user is still blocked from profit loss report (feature: profit_loss)', function () {
    /** @var object{company: Company, user: User, pemula: Plan, profesional: Plan, enterprise: Plan} $this */
    $this->company->subscription()->update(['plan_id' => $this->profesional->id]);

    actingAs($this->user)
        ->get(route('reports.profit-loss'))
        ->assertRedirect(route('subscription.index'));
});

it('profesional user is still blocked from file manager (feature: file_manager)', function () {
    /** @var object{company: Company, user: User, pemula: Plan, profesional: Plan, enterprise: Plan} $this */
    $this->company->subscription()->update(['plan_id' => $this->profesional->id]);
    $this->user->givePermissionTo('view_file_manager');

    actingAs($this->user)
        ->get(route('file-manager.index'))
        ->assertRedirect(route('subscription.index'));
});

it('profesional user can access role and permission management (feature: user_roles)', function () {
    /** @var object{company: Company, user: User, pemula: Plan, profesional: Plan, enterprise: Plan} $this */
    $this->company->subscription()->update(['plan_id' => $this->profesional->id]);

    actingAs($this->user)
        ->get(route('permissions.index'))
        ->assertOk();
});

it('profesional user can access user permissions page (feature: user_roles)', function () {
    /** @var object{company: Company, user: User, pemula: Plan, profesional: Plan, enterprise: Plan} $this */
    $this->company->subscription()->update(['plan_id' => $this->profesional->id]);

    actingAs($this->user)
        ->get(route('user-permissions.index'))
        ->assertOk();
});

// ===========================================================================
// Plan Enterprise — semua fitur bisa diakses
// ===========================================================================

it('enterprise user can access all features', function () {
    /** @var object{company: Company, user: User, pemula: Plan, profesional: Plan, enterprise: Plan} $this */
    $this->company->subscription()->update(['plan_id' => $this->enterprise->id]);
    $this->user->givePermissionTo('view_file_manager');

    $routes = [
        route('purchase.index'),
        route('shift.index'),
        route('reports.purchase.index'),
        route('reports.stock.index'),
        route('reports.profit-loss'),
        route('stock-transfer.index'),
        route('file-manager.index'),
        route('permissions.index'),
        route('role-permissions.index'),
        route('user-permissions.index'),
    ];

    foreach ($routes as $url) {
        actingAs($this->user)
            ->get($url)
            ->assertOk();
    }
});

// ===========================================================================
// Role tenant — super_admin dan admin sama-sama kena pembatasan
// ===========================================================================

it('super_admin (store owner) with pemula plan is blocked from premium features', function () {
    /** @var object{company: Company, user: User, pemula: Plan, profesional: Plan, enterprise: Plan} $this */
    $this->user->syncRoles('super_admin');

    actingAs($this->user)
        ->get(route('purchase.index'))
        ->assertRedirect(route('subscription.index'));
});

it('cashier with pemula plan can access shift management', function () {
    /** @var object{company: Company, user: User, pemula: Plan, profesional: Plan, enterprise: Plan} $this */
    /** @var User $cashier */
    $cashier = User::factory()->create([
        'company_id' => $this->company->id,
        'role' => 'cashier',
    ]);
    $cashier->syncRoles('cashier');

    actingAs($cashier)
        ->get(route('shift.index'))
        ->assertOk();
});

// ===========================================================================
// Request JSON — mengembalikan 403 bukan redirect
// ===========================================================================

it('JSON request to locked route returns 403 instead of redirect', function () {
    /** @var object{company: Company, user: User, pemula: Plan, profesional: Plan, enterprise: Plan} $this */
    actingAs($this->user)
        ->getJson(route('purchase.index'))
        ->assertStatus(403)
        ->assertJsonFragment(['message' => 'Fitur ini tidak tersedia di plan '.$this->pemula->name.'. Upgrade plan Anda untuk mengakses fitur ini.']);
});

// ===========================================================================
// Tidak ada langganan aktif — redirect tanpa membuat subscription baru
// ===========================================================================

it('redirects to subscription page when no active subscription without creating new subscription', function () {
    /** @var object{company: Company, user: User, pemula: Plan, profesional: Plan, enterprise: Plan} $this */
    $this->company->subscription()->delete();

    actingAs($this->user)
        ->get(route('purchase.index'))
        ->assertRedirect(route('subscription.index'));

    expect($this->company->fresh()->activeSubscription())->toBeNull();
});

// ===========================================================================
// Pesan error redirect mengandung nama plan
// ===========================================================================

it('redirect error message includes plan name when blocked', function () {
    /** @var object{company: Company, user: User, pemula: Plan, profesional: Plan, enterprise: Plan} $this */
    actingAs($this->user)
        ->get(route('purchase.index'))
        ->assertRedirect(route('subscription.index'))
        ->assertSessionHas('error', 'Fitur ini tidak tersedia di plan '.$this->pemula->name.'. Upgrade plan Anda untuk mengakses fitur ini.');
});
