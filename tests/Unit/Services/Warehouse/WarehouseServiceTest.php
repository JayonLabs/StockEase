<?php

use App\Models\Warehouse;
use App\Services\Warehouse\WarehouseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('can get paginated warehouses', function () {
    Warehouse::factory()->count(15)->create();
    $service = new WarehouseService;

    $results = $service->getPaginatedWarehouses([], 10);

    expect($results->total())->toBe(15);
    expect($results->count())->toBe(10);
});

it('can filter warehouses by search', function () {
    Warehouse::factory()->create(['name' => 'Gudang Pusat']);
    Warehouse::factory()->create(['name' => 'Toko A']);
    Warehouse::factory()->create(['name' => 'Toko B']);
    $service = new WarehouseService;

    $results = $service->getPaginatedWarehouses(['search' => 'Pusat'], 10);

    expect($results->total())->toBe(1);
    expect($results->first()->name)->toBe('Gudang Pusat');
});

it('can store a new warehouse', function () {
    $service = new WarehouseService;
    $data = [
        'name' => 'Gudang Baru',
        'address' => 'Jl. Baru No. 1',
        'phone' => '021-123456',
        'is_active' => true,
    ];

    $warehouse = $service->storeWarehouse($data);

    expect($warehouse->name)->toBe('Gudang Baru');
    expect($warehouse->slug)->toBe('gudang-baru');
    expect($warehouse->address)->toBe('Jl. Baru No. 1');
    expect($warehouse->phone)->toBe('021-123456');
    expect($warehouse->is_active)->toBeTrue();

    $this->assertDatabaseHas('warehouses', [
        'name' => 'Gudang Baru',
        'slug' => 'gudang-baru',
    ]);
});

it('can store a warehouse with minimal data', function () {
    $service = new WarehouseService;
    $warehouse = $service->storeWarehouse(['name' => 'Gudang Minimal']);

    expect($warehouse->name)->toBe('Gudang Minimal');
    expect($warehouse->is_active)->toBeTrue();
});

it('can update a warehouse', function () {
    $warehouse = Warehouse::factory()->create(['name' => 'Nama Lama']);
    $service = new WarehouseService;

    $service->updateWarehouse($warehouse, [
        'name' => 'Nama Baru',
        'address' => 'Alamat Baru',
        'phone' => '021-999',
        'is_active' => false,
    ]);

    $warehouse->refresh();
    expect($warehouse->name)->toBe('Nama Baru');
    expect($warehouse->slug)->toBe('nama-baru');
    expect($warehouse->address)->toBe('Alamat Baru');
    expect($warehouse->phone)->toBe('021-999');
    expect($warehouse->is_active)->toBeFalse();
});

it('does not change slug if name unchanged on update', function () {
    $warehouse = Warehouse::factory()->create(['name' => 'Same', 'slug' => 'same-old']);
    $service = new WarehouseService;

    $service->updateWarehouse($warehouse, ['name' => 'Same']);

    $warehouse->refresh();
    expect($warehouse->slug)->toBe('same-old');
});

it('can delete a warehouse', function () {
    $warehouse = Warehouse::factory()->create();
    $service = new WarehouseService;

    $service->deleteWarehouse($warehouse);

    $this->assertSoftDeleted('warehouses', ['id' => $warehouse->id]);
});
