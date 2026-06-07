<?php

namespace Tests\Feature\General;

use App\Models\Product;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;

uses(LazilyRefreshDatabase::class);

it('renders admin dashboard with correct data', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);

    // Create sales for today and this month
    Sale::factory()->create([
        'user_id' => $admin->id,
        'total' => 1000,
        'date' => Carbon::today()->toDateString(),
    ]);
    Sale::factory()->create([
        'user_id' => $admin->id,
        'total' => 2000,
        'date' => Carbon::now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
    ]); // Should not be in this month's total

    // Create low stock products
    Product::factory()->create(['name' => 'Low Stock Item', 'stock' => 5, 'alert_stock' => 10]);

    $response = actingAs($admin)->get(route('dashboard'));

    $response->assertInertia(
        fn ($page) => $page
            ->component('Dashboard/Index')
            ->has(
                'data.salesSummary',
                fn ($json) => $json
                    ->where('today', 1000)
                    ->where('month', 1000)
                    ->has('activeProducts')
                    ->has('monthPurchases')
            )
            ->has('data.lowStock', 1)
            ->where('data.lowStock.0.name', 'Low Stock Item')
            ->has('data.activities')
    );
});

it('renders cashier dashboard with correct data', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);

    // Create a sale for today
    $product = Product::factory()->create(['name' => 'Best Seller']);
    $sale = Sale::factory()->create([
        'user_id' => $cashier->id,
        'total' => 5000,
        'date' => Carbon::today()->toDateString(),
        'payment_method' => 'cash',
    ]);
    $sale->saleItems()->create(['product_id' => $product->id, 'qty' => 2, 'price' => 2500]);

    $response = actingAs($cashier)->get(route('dashboard'));

    $response->assertInertia(
        fn ($page) => $page
            ->component('Dashboard/Index')
            ->has(
                'data.cashierSalesSummary',
                fn ($json) => $json
                    ->where('todaysIncome', 5000)
                    ->where('bestSellingProduct', 'Best Seller')
                    ->etc()
            )
            ->has('data.recentTransaction', 1)
    );
});

it('renders warehouse dashboard with correct data', function () {
    /** @var User $warehouse */
    $warehouse = User::factory()->create(['role' => 'warehouse']);

    Product::factory()->count(3)->create();
    Supplier::factory()->count(2)->create();

    $response = actingAs($warehouse)->get(route('dashboard'));

    $response->assertInertia(
        fn ($page) => $page
            ->component('Dashboard/Index')
            ->has(
                'data.warehouseSummary',
                fn ($json) => $json
                    ->where('totalProduct', 3)
                    ->where('activeSupplier', 2)
                    ->etc()
            )
            ->has('data.activityLogWarehouse')
    );
});
