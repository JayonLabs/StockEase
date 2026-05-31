<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Services\Product\PromotionService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->service = new PromotionService;
});

it('gets paginated promotions', function () {
    Promotion::factory()->count(15)->create();

    $result = $this->service->getPaginatedPromotions([], 10);

    expect($result->total())->toBe(15);
    expect($result->count())->toBe(10);
});

it('filters promotions by search query on name', function () {
    Promotion::factory()->create(['name' => 'Diskon Lebaran']);
    Promotion::factory()->create(['name' => 'Promo Akhir Tahun']);

    $result = $this->service->getPaginatedPromotions(['search' => 'Lebaran']);

    expect($result->total())->toBe(1);
    expect($result->first()->name)->toBe('Diskon Lebaran');
});

it('filters promotions by search query on product name', function () {
    $product = Product::factory()->create(['name' => 'Indomie']);
    Promotion::factory()->create(['product_id' => $product->id, 'name' => 'Promo Indomie']);
    Promotion::factory()->create(['name' => 'Promo Lain']);

    $result = $this->service->getPaginatedPromotions(['search' => 'Indomie']);

    expect($result->total())->toBe(1);
    expect($result->first()->name)->toBe('Promo Indomie');
});

it('filters promotions by search query on category name', function () {
    $category = Category::factory()->create(['name' => 'Minuman']);
    Promotion::factory()->create(['category_id' => $category->id, 'name' => 'Diskon Minuman']);
    Promotion::factory()->create(['name' => 'Diskon Makanan']);

    $result = $this->service->getPaginatedPromotions(['search' => 'Minuman']);

    expect($result->total())->toBe(1);
    expect($result->first()->name)->toBe('Diskon Minuman');
});

it('filters promotions by start date', function () {
    Promotion::factory()->create(['start_date' => now()->subDays(10)]);
    Promotion::factory()->create(['start_date' => now()->addDays(10)]);

    $result = $this->service->getPaginatedPromotions(['start' => now()->toDateString()]);

    expect($result->total())->toBe(1);
});

it('filters promotions by end date', function () {
    Promotion::factory()->create(['end_date' => now()->addDays(1)]);
    Promotion::factory()->create(['end_date' => now()->addDays(30)]);

    $result = $this->service->getPaginatedPromotions(['end' => now()->addDays(2)->toDateString()]);

    expect($result->total())->toBe(1);
});

it('returns empty paginator when no promotions match filters', function () {
    Promotion::factory()->create(['name' => 'Diskon Lebaran']);

    $result = $this->service->getPaginatedPromotions(['search' => 'TidakAda']);

    expect($result->total())->toBe(0);
});

it('loads category and product relationships', function () {
    $category = Category::factory()->create(['name' => 'Makanan']);
    $product = Product::factory()->create(['name' => 'Roti']);
    Promotion::factory()->create([
        'category_id' => $category->id,
        'product_id' => $product->id,
    ]);

    $result = $this->service->getPaginatedPromotions([]);

    $promotion = $result->first();
    expect($promotion->relationLoaded('category'))->toBeTrue();
    expect($promotion->relationLoaded('product'))->toBeTrue();
    expect($promotion->category->name)->toBe('Makanan');
    expect($promotion->product->name)->toBe('Roti');
});
