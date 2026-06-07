<?php

use App\Enums\Role;
use App\Enums\SaleStatus;
use App\Enums\ShiftStatus;
use App\Models\Company;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticated;
use function Pest\Laravel\get;
use function Pest\Laravel\seed;

uses(LazilyRefreshDatabase::class);

// ---------------------------------------------------------------------------
// Shared Helpers
// ---------------------------------------------------------------------------

function createUserWithRole(Role $role): User
{
    return User::factory()->create(['role' => $role->value]);
}

/**
 * Create a company owner (super_admin) — simulates a newly registered user.
 */
function createCompanyOwner(): User
{
    $company = Company::create([
        'name' => 'Toko Baru',
        'slug' => 'toko-baru-'.uniqid(),
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'super_admin',
    ]);

    $company->update(['owner_id' => $user->id]);

    return $user;
}

function seedRolesAndPermissions(): void
{
    seed(RoleAndPermissionSeeder::class);
}

// ---------------------------------------------------------------------------
// POS Page Access
// ---------------------------------------------------------------------------

describe('POS — Page Access', function () {
    beforeEach(function () {
        seedRolesAndPermissions();
    });

    it('allows super_admin to access POS index page', function () {
        $user = createUserWithRole(Role::SuperAdmin);

        actingAs($user)
            ->get(route('pos.index'))
            ->assertSuccessful();
    });

    it('allows admin to access POS index page', function () {
        $user = createUserWithRole(Role::Admin);

        actingAs($user)
            ->get(route('pos.index'))
            ->assertSuccessful();
    });

    it('allows cashier to access POS index page', function () {
        $user = createUserWithRole(Role::Cashier);

        actingAs($user)
            ->get(route('pos.index'))
            ->assertSuccessful();
    });

    it('blocks warehouse from POS index page', function () {
        $user = createUserWithRole(Role::Warehouse);

        actingAs($user)
            ->get(route('pos.index'))
            ->assertForbidden();
    });

    it('redirects unauthenticated users to login', function () {
        get(route('pos.index'))
            ->assertRedirect(route('login'));
    });
});

// ---------------------------------------------------------------------------
// POS Access for Newly Registered Company Owner
// ---------------------------------------------------------------------------

describe('POS — Company Owner Access (after registration)', function () {
    beforeEach(function () {
        seedRolesAndPermissions();
    });

    it('allows newly registered company owner (super_admin) to access POS', function () {
        $user = createCompanyOwner();

        actingAs($user)
            ->get(route('pos.index'))
            ->assertSuccessful();
    });

    it('shows correct Inertia component for company owner accessing POS', function () {
        $user = createCompanyOwner();

        actingAs($user)
            ->get(route('pos.index'))
            ->assertInertia(fn ($page) => $page->component('Pos/Index'));
    });

    it('returns empty products and categories for new company owner', function () {
        $user = createCompanyOwner();

        actingAs($user)
            ->get(route('pos.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Pos/Index')
                ->has('products')
                ->has('categories')
                ->has('cart')
                ->has('warehouses')
                ->where('hasActiveShift', false)
            );
    });
});

// ---------------------------------------------------------------------------
// Shift Page Access (route was missing super_admin in middleware string)
// ---------------------------------------------------------------------------

describe('Shift — Page Access', function () {
    beforeEach(function () {
        seedRolesAndPermissions();
    });

    it('allows super_admin to access shift index page', function () {
        $user = createUserWithRole(Role::SuperAdmin);

        actingAs($user)
            ->get(route('shift.index'))
            ->assertSuccessful();
    });

    it('allows admin to access shift index page', function () {
        $user = createUserWithRole(Role::Admin);

        actingAs($user)
            ->get(route('shift.index'))
            ->assertSuccessful();
    });

    it('allows cashier to access shift index page', function () {
        $user = createUserWithRole(Role::Cashier);

        actingAs($user)
            ->get(route('shift.index'))
            ->assertSuccessful();
    });

    it('blocks warehouse from shift index page', function () {
        $user = createUserWithRole(Role::Warehouse);

        actingAs($user)
            ->get(route('shift.index'))
            ->assertForbidden();
    });
});

// ---------------------------------------------------------------------------
// POS Add-To-Cart — FormRequest Authorization
// ---------------------------------------------------------------------------

describe('POS — Add To Cart Authorization', function () {
    beforeEach(function () {
        seedRolesAndPermissions();
    });

    it('allows super_admin to add item to cart', function () {
        $user = createUserWithRole(Role::SuperAdmin);
        $product = Product::factory()->create();
        openShift($user);
        setupWarehouse($user);

        $response = actingAs($user)
            ->postJson(route('pos.add-to-cart'), [
                'product_id' => $product->id,
                'qty' => 1,
            ]);

        // Authorization passes — 400 may occur due to warehouse stock but must NOT be 403
        expect($response->status())->not->toBe(403);
    });

    it('allows admin to add item to cart', function () {
        $user = createUserWithRole(Role::Admin);
        $product = Product::factory()->create();
        openShift($user);
        setupWarehouse($user);

        $response = actingAs($user)
            ->postJson(route('pos.add-to-cart'), [
                'product_id' => $product->id,
                'qty' => 1,
            ]);

        expect($response->status())->not->toBe(403);
    });

    it('allows cashier to add item to cart', function () {
        $user = createUserWithRole(Role::Cashier);
        $product = Product::factory()->create();
        openShift($user);
        setupWarehouse($user);

        $response = actingAs($user)
            ->postJson(route('pos.add-to-cart'), [
                'product_id' => $product->id,
                'qty' => 1,
            ]);

        expect($response->status())->not->toBe(403);
    });

    it('blocks warehouse from adding item to cart', function () {
        $user = createUserWithRole(Role::Warehouse);
        $product = Product::factory()->create();

        actingAs($user)
            ->postJson(route('pos.add-to-cart'), [
                'product_id' => $product->id,
                'qty' => 1,
            ])
            ->assertForbidden();
    });
});

// ---------------------------------------------------------------------------
// POS Barcode Authorization
// ---------------------------------------------------------------------------

describe('POS — Barcode Authorization', function () {
    beforeEach(function () {
        seedRolesAndPermissions();
    });

    it('allows super_admin to add item by barcode', function () {
        $user = createUserWithRole(Role::SuperAdmin);
        $product = Product::factory()->create(['barcode' => '1234567890123']);
        openShift($user);
        setupWarehouse($user);

        $response = actingAs($user)
            ->postJson(route('pos.add-to-cart-barcode'), [
                'barcode' => $product->barcode,
                'qty' => 1,
            ]);

        expect($response->status())->not->toBe(403);
    });

    it('blocks warehouse from adding item by barcode', function () {
        $user = createUserWithRole(Role::Warehouse);
        $product = Product::factory()->create(['barcode' => '1234567890123']);

        actingAs($user)
            ->postJson(route('pos.add-to-cart-barcode'), [
                'barcode' => $product->barcode,
                'qty' => 1,
            ])
            ->assertForbidden();
    });
});

// ---------------------------------------------------------------------------
// POS Change Qty Authorization
// ---------------------------------------------------------------------------

describe('POS — Change Qty Authorization', function () {
    beforeEach(function () {
        seedRolesAndPermissions();
    });

    it('allows super_admin to change item quantity', function () {
        $user = createUserWithRole(Role::SuperAdmin);
        $product = Product::factory()->create();
        openShift($user);
        setupWarehouse($user);

        // Add item first (will fail on stock but auth passes)
        actingAs($user)->postJson(route('pos.add-to-cart'), [
            'product_id' => $product->id,
            'qty' => 2,
        ]);

        $response = actingAs($user)
            ->patchJson(route('pos.change-qty'), [
                'product_id' => $product->id,
                'qty' => 5,
            ]);

        expect($response->status())->not->toBe(403);
    });

    it('blocks warehouse from changing item quantity', function () {
        $user = createUserWithRole(Role::Warehouse);
        $product = Product::factory()->create();

        actingAs($user)
            ->patchJson(route('pos.change-qty'), [
                'product_id' => $product->id,
                'qty' => 5,
            ])
            ->assertForbidden();
    });
});

// ---------------------------------------------------------------------------
// POS Checkout Authorization
// ---------------------------------------------------------------------------

describe('POS — Checkout Authorization', function () {
    beforeEach(function () {
        seedRolesAndPermissions();
    });

    it('allows super_admin to pass checkout form request authorization', function () {
        $user = createUserWithRole(Role::SuperAdmin);
        $product = Product::factory()->create();
        openShift($user);
        setupWarehouse($user);

        actingAs($user)->postJson(route('pos.add-to-cart'), [
            'product_id' => $product->id,
            'qty' => 1,
        ]);

        $response = actingAs($user)
            ->putJson(route('pos.checkout'), [
                'payment_method' => 'cash',
                'paid' => 999999,
            ]);

        // Must NOT get 403 Forbidden — authorization passes
        expect($response->status())->not->toBe(403);
    });

    it('blocks warehouse from checkout', function () {
        $user = createUserWithRole(Role::Warehouse);

        actingAs($user)
            ->putJson(route('pos.checkout'), [
                'payment_method' => 'cash',
                'paid' => 999999,
            ])
            ->assertForbidden();
    });
});

// ---------------------------------------------------------------------------
// POS Send Invoice Authorization
// ---------------------------------------------------------------------------

describe('POS — Send Invoice Authorization', function () {
    beforeEach(function () {
        seedRolesAndPermissions();
    });

    it('allows super_admin to pass send invoice form request authorization', function () {
        $user = createUserWithRole(Role::SuperAdmin);

        $sale = Sale::factory()->create([
            'user_id' => $user->id,
            'status' => SaleStatus::Completed->value,
        ]);

        $response = actingAs($user)
            ->postJson(route('pos.send-invoice'), [
                'sale_id' => $sale->id,
                'email' => 'customer@example.com',
            ]);

        expect($response->status())->not->toBe(403);
    });

    it('blocks warehouse from sending invoice', function () {
        $user = createUserWithRole(Role::Warehouse);

        actingAs($user)
            ->postJson(route('pos.send-invoice'), [
                'sale_id' => 1,
                'email' => 'customer@example.com',
            ])
            ->assertForbidden();
    });
});

// ---------------------------------------------------------------------------
// POS QRIS Token — Middleware Authorization
// ---------------------------------------------------------------------------

describe('POS — QRIS Token Middleware Authorization', function () {
    beforeEach(function () {
        seedRolesAndPermissions();
    });

    it('allows super_admin to request QRIS token', function () {
        $user = createUserWithRole(Role::SuperAdmin);

        $response = actingAs($user)
            ->postJson(route('pos.qris-token'), [
                'amount' => 50000,
                'customer_name' => 'Test Customer',
            ]);

        expect($response->status())->not->toBe(403);
    });

    it('blocks warehouse from requesting QRIS token', function () {
        $user = createUserWithRole(Role::Warehouse);

        actingAs($user)
            ->postJson(route('pos.qris-token'), [
                'amount' => 50000,
            ])
            ->assertForbidden();
    });
});

// ---------------------------------------------------------------------------
// Login → POS Flow
// ---------------------------------------------------------------------------

describe('POS — Authenticated user can login and access POS', function () {
    beforeEach(function () {
        seedRolesAndPermissions();
    });

    it('super_admin can login then access POS page', function () {
        $user = createUserWithRole(Role::SuperAdmin);

        $response = actingAs($user)->get(route('pos.index'));

        $response->assertSuccessful();
        assertAuthenticated();
    });

    it('admin can login then access POS page', function () {
        $user = createUserWithRole(Role::Admin);

        actingAs($user)
            ->get(route('pos.index'))
            ->assertSuccessful();
    });

    it('cashier can login then access POS page', function () {
        $user = createUserWithRole(Role::Cashier);

        actingAs($user)
            ->get(route('pos.index'))
            ->assertSuccessful();
    });
});

// ---------------------------------------------------------------------------
// Sale Return — FormRequest Authorization
// ---------------------------------------------------------------------------

describe('Sale Return — FormRequest Authorization', function () {
    beforeEach(function () {
        seedRolesAndPermissions();
    });

    it('allows super_admin to pass sale return form request authorization', function () {
        $user = createUserWithRole(Role::SuperAdmin);

        $sale = Sale::factory()->create([
            'user_id' => $user->id,
            'status' => SaleStatus::Completed->value,
        ]);

        $response = actingAs($user)
            ->postJson(route('sale-return.store', ['sale' => $sale->id]), [
                'return_type' => 'refund',
                'items' => [
                    ['sale_item_id' => 1, 'qty' => 1],
                ],
            ]);

        // Authorization should pass; may fail on validation but NOT 403
        expect($response->status())->not->toBe(403);
    });

    it('blocks warehouse from creating sale return', function () {
        $user = createUserWithRole(Role::Warehouse);
        $adminUser = createUserWithRole(Role::Admin);
        $sale = Sale::factory()->create([
            'user_id' => $adminUser->id,
            'status' => SaleStatus::Completed->value,
        ]);

        actingAs($user)
            ->postJson(route('sale-return.store', ['sale' => $sale->id]), [
                'return_type' => 'refund',
                'items' => [
                    ['sale_item_id' => 1, 'qty' => 1],
                ],
            ])
            ->assertForbidden();
    });
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function setupWarehouse(User $user): void
{
    $warehouse = Warehouse::factory()->create(['is_active' => true]);

    actingAs($user)
        ->postJson(route('pos.set-warehouse'), [
            'warehouse_id' => $warehouse->id,
        ]);
}

function openShift(User $user): Shift
{
    return Shift::factory()->create([
        'user_id' => $user->id,
        'status' => ShiftStatus::Open->value,
        'starting_cash' => 100000,
        'opened_at' => now(),
    ]);
}
