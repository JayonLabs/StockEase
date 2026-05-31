<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\actingAs;

uses(LazilyRefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Authorization Tests
|--------------------------------------------------------------------------
*/

it('allows super_admin to reset user password', function () {
    /** @var User $superAdmin */
    $superAdmin = User::factory()->create(['role' => 'super_admin']);
    /** @var User $user */
    $user = User::factory()->create();

    actingAs($superAdmin)
        ->put(route('users.reset-password', $user), [
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])
        ->assertRedirect();
});

it('allows admin to reset user password', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    /** @var User $user */
    $user = User::factory()->create();

    actingAs($admin)
        ->put(route('users.reset-password', $user), [
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])
        ->assertRedirect();
});

it('denies cashier from resetting user password', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);
    /** @var User $user */
    $user = User::factory()->create();

    actingAs($cashier)
        ->put(route('users.reset-password', $user), [
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])
        ->assertForbidden();
});

it('denies warehouse role from resetting user password', function () {
    /** @var User $warehouse */
    $warehouse = User::factory()->create(['role' => 'warehouse']);
    /** @var User $user */
    $user = User::factory()->create();

    actingAs($warehouse)
        ->put(route('users.reset-password', $user), [
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Successful Reset Tests
|--------------------------------------------------------------------------
*/

it('resets password and hashes the new value', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    /** @var User $user */
    $user = User::factory()->create();

    actingAs($admin)
        ->put(route('users.reset-password', $user), [
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Password berhasil diubah');

    expect(Hash::check('NewPassword123!', $user->refresh()->password))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Password Validation Tests
|--------------------------------------------------------------------------
*/

it('requires password field', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    /** @var User $user */
    $user = User::factory()->create();

    actingAs($admin)
        ->put(route('users.reset-password', $user), [])
        ->assertSessionHasErrors(['password']);
});

it('requires password confirmation', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    /** @var User $user */
    $user = User::factory()->create();

    actingAs($admin)
        ->put(route('users.reset-password', $user), [
            'password' => 'NewPassword123!',
        ])
        ->assertSessionHasErrors(['password']);
});

it('rejects password when confirmation does not match', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    /** @var User $user */
    $user = User::factory()->create();

    actingAs($admin)
        ->put(route('users.reset-password', $user), [
            'password' => 'NewPassword123!',
            'password_confirmation' => 'DifferentPassword123!',
        ])
        ->assertSessionHasErrors(['password']);
});

it('rejects password shorter than 8 characters', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    /** @var User $user */
    $user = User::factory()->create();

    actingAs($admin)
        ->put(route('users.reset-password', $user), [
            'password' => 'Ab1!',
            'password_confirmation' => 'Ab1!',
        ])
        ->assertSessionHasErrors(['password']);
});

it('rejects password without uppercase letters', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    /** @var User $user */
    $user = User::factory()->create();

    actingAs($admin)
        ->put(route('users.reset-password', $user), [
            'password' => 'newpassword123!',
            'password_confirmation' => 'newpassword123!',
        ])
        ->assertSessionHasErrors(['password']);
});

it('rejects password without lowercase letters', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    /** @var User $user */
    $user = User::factory()->create();

    actingAs($admin)
        ->put(route('users.reset-password', $user), [
            'password' => 'NEWPASSWORD123!',
            'password_confirmation' => 'NEWPASSWORD123!',
        ])
        ->assertSessionHasErrors(['password']);
});

it('rejects password without numbers', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    /** @var User $user */
    $user = User::factory()->create();

    actingAs($admin)
        ->put(route('users.reset-password', $user), [
            'password' => 'NewPassword!',
            'password_confirmation' => 'NewPassword!',
        ])
        ->assertSessionHasErrors(['password']);
});

it('rejects password without symbols', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    /** @var User $user */
    $user = User::factory()->create();

    actingAs($admin)
        ->put(route('users.reset-password', $user), [
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])
        ->assertSessionHasErrors(['password']);
});

it('rejects common weak passwords like "password"', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    /** @var User $user */
    $user = User::factory()->create();

    actingAs($admin)
        ->put(route('users.reset-password', $user), [
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertSessionHasErrors(['password']);
});

it('accepts a strong password meeting all complexity requirements', function (string $password) {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    /** @var User $user */
    $user = User::factory()->create();

    actingAs($admin)
        ->put(route('users.reset-password', $user), [
            'password' => $password,
            'password_confirmation' => $password,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
})->with([
    'standard strong password' => 'NewPassword123!',
    'with @ symbol' => 'SecurePass1@',
    'with # symbol' => 'MyP@ssw0rd#',
]);
