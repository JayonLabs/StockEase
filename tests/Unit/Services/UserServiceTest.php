<?php

use App\Models\User;
use App\Services\User\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** @property UserService $userService */
uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->userService = new UserService;
});

it('can get paginated users', function () {
    User::factory()->count(15)->create();

    $users = $this->userService->getPaginatedUsers([], 10);

    expect($users->total())->toBe(15 + 0); // No initial users unless seeded
    expect($users->count())->toBe(10);
});

it('can filter users by search', function () {
    User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
    User::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);

    $results = $this->userService->getPaginatedUsers(['search' => 'John']);

    expect($results->total())->toBe(1);
    expect($results->first()->name)->toBe('John Doe');
});

it('can store a new user', function () {
    $data = [
        'name' => 'New User',
        'email' => 'new@example.com',
        'password' => 'password123',
        'role' => 'cashier',
    ];

    $user = $this->userService->storeUser($data);

    expect($user->name)->toBe('New User');
    expect($user->email)->toBe('new@example.com');
    expect(Hash::check('password123', $user->password))->toBeTrue();
    $this->assertDatabaseHas('users', ['email' => 'new@example.com']);
});

it('can update an existing user', function () {
    $user = User::factory()->create(['name' => 'Old Name']);
    $data = ['name' => 'Updated Name'];

    $this->userService->updateUser($user, $data);

    expect($user->fresh()->name)->toBe('Updated Name');
});

it('can reset user password', function () {
    $user = User::factory()->create(['password' => Hash::make('old_password')]);

    $this->userService->resetPassword($user, 'new_password');

    expect(Hash::check('new_password', $user->fresh()->password))->toBeTrue();
});

it('can delete a user', function () {
    $user = User::factory()->create();

    $this->userService->deleteUser($user);

    $this->assertSoftDeleted('users', ['id' => $user->id]);
});
