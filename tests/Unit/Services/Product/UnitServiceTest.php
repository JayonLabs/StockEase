<?php

use App\Models\Unit;
use App\Services\Product\UnitService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/** @property UnitService $unitService */
uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->unitService = new UnitService;
});

it('can get paginated units', function () {
    // There are 15 units seeded via migration
    $initialCount = Unit::count();

    Unit::factory()->count(15)->create();

    $units = $this->unitService->getPaginatedUnits([], 10);

    expect($units->total())->toBe($initialCount + 15);
    expect($units->count())->toBe(10);
});

it('can filter units by search', function () {
    // 'Kilogram (kg)' already exists from migration, let's use a unique name
    Unit::factory()->create(['name' => 'Custom Kilogram', 'short_name' => 'C-Kg']);
    Unit::factory()->create(['name' => 'Custom Gram', 'short_name' => 'C-g']);

    $units = $this->unitService->getPaginatedUnits(['search' => 'Custom']);

    expect($units->total())->toBe(2);
    expect($units->first()->name)->toContain('Custom');
});

it('can store a new unit', function () {
    $data = [
        'name' => 'Unique Pcs', // Avoid 'Pcs' which exists
        'short_name' => 'UPcs',
    ];

    $unit = $this->unitService->storeUnit($data);

    expect($unit->name)->toBe('Unique Pcs');
    expect($unit->slug)->toBe('unique-pcs');
    $this->assertDatabaseHas('units', ['name' => 'Unique Pcs']);
});

it('can update an existing unit', function () {
    $unit = Unit::factory()->create(['name' => 'Old Name', 'slug' => 'old-name']);
    $data = [
        'name' => 'New Name',
        'short_name' => 'NN',
    ];

    $this->unitService->updateUnit($unit, $data);

    expect($unit->fresh()->name)->toBe('New Name');
    expect($unit->fresh()->slug)->toBe('new-name');
});

it('can delete a unit', function () {
    $unit = Unit::factory()->create();

    $this->unitService->deleteUnit($unit);

    $this->assertSoftDeleted('units', ['id' => $unit->id]);
});
