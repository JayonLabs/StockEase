<?php

use App\Models\Category;
use App\Services\Product\CategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** @property CategoryService $service */
uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new CategoryService;
});

it('can get paginated categories', function () {
    Category::factory()->count(15)->create();

    $result = $this->service->getPaginatedCategories([], 10);

    expect($result->items())->toHaveCount(10);
    expect($result->total())->toBe(15);
});

it('can filter categories by name', function () {
    Category::factory()->create(['name' => 'Electronic']);
    Category::factory()->create(['name' => 'Fashion']);

    $result = $this->service->getPaginatedCategories(['search' => 'Elect']);

    expect($result->items())->toHaveCount(1);
    expect($result->items()[0]->name)->toBe('Electronic');
});

it('can store a category', function () {
    $data = ['name' => 'New Category'];

    $category = $this->service->storeCategory($data);

    expect($category)->toBeInstanceOf(Category::class);
    expect($category->name)->toBe('New Category');
    expect($category->slug)->toBe('new-category');
    $this->assertDatabaseHas('categories', ['name' => 'New Category']);
});

it('can update a category', function () {
    $category = Category::factory()->create(['name' => 'Old Category']);
    $data = ['name' => 'Updated Category'];

    $this->service->updateCategory($category, $data);

    $category->refresh();
    expect($category->name)->toBe('Updated Category');
    expect($category->slug)->toBe('updated-category');
});

it('can delete a category', function () {
    $category = Category::factory()->create();

    $this->service->deleteCategory($category);

    $this->assertSoftDeleted('categories', ['id' => $category->id]);
});
