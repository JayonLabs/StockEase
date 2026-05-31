<?php

use App\Models\Supplier;
use App\Services\Purchase\SupplierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** @property SupplierService $service */
uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new SupplierService;
});

it('can get paginated suppliers', function () {
    Supplier::factory()->count(15)->create();

    $result = $this->service->getPaginatedSuppliers([], 10);

    expect($result->items())->toHaveCount(10);
    expect($result->total())->toBe(15);
});

it('can filter suppliers by search query', function () {
    Supplier::factory()->create(['name' => 'Supplier A', 'address' => 'Jakarta']);
    Supplier::factory()->create(['name' => 'Supplier B', 'phone' => '08123456789']);

    // Search by name
    $result = $this->service->getPaginatedSuppliers(['search' => 'Supplier A']);
    expect($result->items())->toHaveCount(1);

    // Search by address
    $result = $this->service->getPaginatedSuppliers(['search' => 'Jakarta']);
    expect($result->items())->toHaveCount(1);

    // Search by phone
    $result = $this->service->getPaginatedSuppliers(['search' => '08123456789']);
    expect($result->items())->toHaveCount(1);
});

it('can store a supplier', function () {
    $data = [
        'name' => 'New Supplier',
        'phone' => '0811223344',
        'address' => 'Bandung',
    ];

    $supplier = $this->service->storeSupplier($data);

    expect($supplier)->toBeInstanceOf(Supplier::class);
    expect($supplier->name)->toBe('New Supplier');
    expect($supplier->slug)->toBe('new-supplier');
    $this->assertDatabaseHas('suppliers', ['name' => 'New Supplier']);
});

it('can update a supplier', function () {
    $supplier = Supplier::factory()->create(['name' => 'Old Supplier']);
    $data = ['name' => 'Updated Supplier'];

    $this->service->updateSupplier($supplier, $data);

    $supplier->refresh();
    expect($supplier->name)->toBe('Updated Supplier');
    expect($supplier->slug)->toBe('updated-supplier');
});

it('can delete a supplier', function () {
    $supplier = Supplier::factory()->create();

    $this->service->deleteSupplier($supplier);

    $this->assertSoftDeleted('suppliers', ['id' => $supplier->id]);
});
