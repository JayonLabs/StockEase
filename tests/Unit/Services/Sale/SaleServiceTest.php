<?php

use App\Models\Sale;
use App\Models\User;
use App\Services\Sale\SaleService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

it('can get paginated sales history', function () {
    // Create sales that are completed/not pending
    Sale::factory()->count(15)->create([
        'payment_method' => 'cash',
        'status' => 'completed',
    ]);

    // Create one pending to ensure it is excluded
    Sale::factory()->create(['payment_method' => 'pending']);

    $saleService = new SaleService;
    $sales = $saleService->getPaginatedSales([], 10);

    expect($sales->total())->toBe(15);
    expect($sales->count())->toBe(10);
});

it('can filter sales by search query', function () {
    $user = User::factory()->create(['name' => 'Test User', 'email' => 'test@example.com']);
    Sale::factory()->create(['user_id' => $user->id, 'customer_name' => 'John Doe', 'payment_method' => 'cash', 'status' => 'completed']);
    Sale::factory()->create(['user_id' => $user->id, 'customer_name' => 'Jane Smith', 'payment_method' => 'cash', 'status' => 'completed']);

    $saleService = new SaleService;
    $sales = $saleService->getPaginatedSales(['search' => 'John']);

    expect($sales->total())->toBe(1);
    expect($sales->first()->customer_name)->toBe('John Doe');
});

it('can filter sales by date range', function () {
    $sale1 = Sale::factory()->create(['date' => now()->subDays(5), 'payment_method' => 'cash', 'status' => 'completed']);
    $sale2 = Sale::factory()->create(['date' => now(), 'payment_method' => 'cash', 'status' => 'completed']);

    $filters = [
        'start' => now()->subDays(1)->toDateString(),
        'end' => now()->toDateString(),
    ];

    $saleService = new SaleService;
    $sales = $saleService->getPaginatedSales($filters);

    expect($sales->total())->toBe(1);
    expect($sales->first()->id)->toBe($sale2->id);
});

it('can get sale details with relations', function () {
    $sale = Sale::factory()->create(['payment_method' => 'cash', 'status' => 'completed']);

    $saleService = new SaleService;
    $details = $saleService->getSaleDetails($sale);

    expect($details->relationLoaded('user'))->toBeTrue();
    expect($details->relationLoaded('saleItems'))->toBeTrue();
});

it('eager loads user roles in paginated sales to prevent n plus one', function () {
    $user = User::factory()->create(['role' => 'admin']);
    Sale::factory()->count(3)->create(['user_id' => $user->id, 'payment_method' => 'cash']);

    $saleService = new SaleService;
    $sales = $saleService->getPaginatedSales([], 10);

    $firstSale = $sales->first();
    expect($firstSale->relationLoaded('user'))->toBeTrue();
    expect($firstSale->user->relationLoaded('roles'))->toBeTrue();
});

it('eager loads user roles in sale details to prevent n plus one', function () {
    $sale = Sale::factory()->create(['payment_method' => 'cash', 'status' => 'completed']);

    $saleService = new SaleService;
    $details = $saleService->getSaleDetails($sale);

    expect($details->user->relationLoaded('roles'))->toBeTrue();
});

it('does not trigger duplicate roles queries when serializing paginated sales', function () {
    $user = User::factory()->create(['role' => 'admin']);
    Sale::factory()->count(3)->create(['user_id' => $user->id, 'payment_method' => 'cash']);

    DB::enableQueryLog();

    $saleService = new SaleService;
    $sales = $saleService->getPaginatedSales([], 10);

    // Force serialization to trigger any lazy-loaded relations
    json_encode($sales->toArray());

    $roleQueries = collect(DB::getQueryLog())
        ->filter(fn ($query) => str_contains($query['query'], 'model_has_roles'));

    expect($roleQueries)->toHaveCount(1);
});
