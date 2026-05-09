<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('allows user to upload photo profile', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $file = UploadedFile::fake()->image('profile.jpg');

    $response = $this->actingAs($user)
        ->postJson(route('profile.photo-profile'), [
            'photo_profile' => $file,
        ]);

    $response->assertSuccessful()
        ->assertJsonPath('success', true);

    $user->refresh();
    expect($user->photo_profile)->not->toBeNull();
    Storage::disk('public')->assertExists(str_replace('storage/', '', $user->photo_profile));
});

it('removes old photo when uploading new photo profile', function () {
    Storage::fake('public');
    $user = User::factory()->create(['photo_profile' => 'storage/photo_profile/old.jpg']);
    Storage::disk('public')->put('photo_profile/old.jpg', 'fake content');

    $file = UploadedFile::fake()->image('new.jpg');

    $this->actingAs($user)
        ->postJson(route('profile.photo-profile'), [
            'photo_profile' => $file,
        ]);

    Storage::disk('public')->assertMissing('photo_profile/old.jpg');
    expect($user->refresh()->photo_profile)->toMatch('/storage\/photo_profile\/\d+\.jpg/');
});

it('allows user to delete photo profile', function () {
    Storage::fake('public');
    $user = User::factory()->create(['photo_profile' => 'storage/photo_profile/profile.jpg']);
    Storage::disk('public')->put('photo_profile/profile.jpg', 'fake content');

    $response = $this->actingAs($user)
        ->deleteJson(route('profile.destroy-photo-profile'));

    $response->assertSuccessful()
        ->assertJsonPath('success', true);

    $user->refresh();
    expect($user->photo_profile)->toBeNull();
    Storage::disk('public')->assertMissing('photo_profile/profile.jpg');
});

it('validates photo profile upload', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson(route('profile.photo-profile'), [
            'photo_profile' => 'not-an-image',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('photo_profile');
});

it('validates photo profile file size', function () {
    $user = User::factory()->create();

    $file = UploadedFile::fake()->create('large.jpg', 3000);

    $response = $this->actingAs($user)
        ->postJson(route('profile.photo-profile'), [
            'photo_profile' => $file,
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('photo_profile');
});

it('validates photo profile must be an image', function () {
    $user = User::factory()->create();

    $file = UploadedFile::fake()->create('document.pdf', 100);

    $response = $this->actingAs($user)
        ->postJson(route('profile.photo-profile'), [
            'photo_profile' => $file,
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('photo_profile');
});

it('validates photo profile is required', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson(route('profile.photo-profile'), []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('photo_profile');
});
