<?php

use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\putJson;

uses(LazilyRefreshDatabase::class);

it('requires authentication', function () {
    $unit = Unit::factory()->create();

    putJson(route('unit.update', $unit), ['name' => 'Updated', 'short_name' => 'upd'])
        ->assertUnauthorized();
});

it('validates required fields', function (string $field) {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);
    $unit = Unit::factory()->create();
    $data = ['name' => 'Updated Unit', 'short_name' => 'uu'];
    unset($data[$field]);

    actingAs($user)
        ->putJson(route('unit.update', $unit), $data)
        ->assertJsonValidationErrors([$field]);
})->with(['name', 'short_name']);

it('rejects duplicate name from another unit', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);
    Unit::factory()->create(['name' => 'Existing', 'short_name' => 'ex']);
    $unit = Unit::factory()->create(['name' => 'Other', 'short_name' => 'ot']);

    actingAs($user)
        ->putJson(route('unit.update', $unit), ['name' => 'Existing', 'short_name' => 'new'])
        ->assertJsonValidationErrors(['name']);
});

it('allows updating with same name on same unit', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);
    $uniqueName = 'Unit-'.Str::random(8);
    $uniqueShort = Str::random(5);
    $unit = Unit::factory()->create(['name' => $uniqueName, 'short_name' => $uniqueShort]);

    actingAs($user)
        ->putJson(route('unit.update', $unit), ['name' => $uniqueName, 'short_name' => $uniqueShort])
        ->assertRedirect();
});

it('rejects duplicate short_name from another unit', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);
    Unit::factory()->create(['name' => 'Existing', 'short_name' => 'ex']);
    $unit = Unit::factory()->create(['name' => 'Other', 'short_name' => 'ot']);

    actingAs($user)
        ->putJson(route('unit.update', $unit), ['name' => 'New Name', 'short_name' => 'ex'])
        ->assertJsonValidationErrors(['short_name']);
});
