<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

uses(LazilyRefreshDatabase::class);

it('requires authentication', function () {
    postJson(route('file-manager.store'), [])
        ->assertUnauthorized();
});

it('requires file field', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);

    actingAs($user)
        ->postJson(route('file-manager.store'), [])
        ->assertJsonValidationErrors(['file']);
});

it('rejects unsupported file types', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);
    Storage::fake('public');

    actingAs($user)
        ->postJson(route('file-manager.store'), [
            'file' => [UploadedFile::fake()->create('test.txt', 10)],
        ])
        ->assertJsonValidationErrors(['file.0']);
});

it('rejects file that exceeds maximum size', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);
    Storage::fake('public');

    actingAs($user)
        ->postJson(route('file-manager.store'), [
            'file' => [UploadedFile::fake()->create('big.xlsx', 120 * 1024)],
        ])
        ->assertJsonValidationErrors(['file.0']);
});

it('accepts valid xlsx file', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);
    Storage::fake('public');

    $response = actingAs($user)
        ->postJson(route('file-manager.store'), [
            'file' => [UploadedFile::fake()->create('import.xlsx', 100)],
        ]);

    $response->assertSessionHasNoErrors();
});
