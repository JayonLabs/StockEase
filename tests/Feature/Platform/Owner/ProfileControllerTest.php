<?php

namespace Tests\Feature\Platform\Owner;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;
use function Pest\Laravel\put;
use function Pest\Laravel\seed;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    seed(RoleAndPermissionSeeder::class);
});

// ===========================================================================
// Access Control
// ===========================================================================

it('redirects guests from profile page to login', function () {
    get(route('platform.owner.profile.edit'))
        ->assertRedirect(route('login'));
});

it('redirects guests from profile update to login', function () {
    patch(route('platform.owner.profile.update'), [
        'name' => 'Guest',
        'email' => 'guest@example.com',
    ])->assertRedirect(route('login'));
});

it('redirects guests from password update to login', function () {
    put(route('platform.owner.profile.password'), [
        'current_password' => 'password',
        'password' => 'NewPassword1!',
        'password_confirmation' => 'NewPassword1!',
    ])->assertRedirect(route('login'));
});

it('denies tenant users access to the platform owner profile page', function () {
    /** @var User $tenantUser */
    $tenantUser = User::factory()->create();
    $tenantUser->syncRoles('admin');

    actingAs($tenantUser)
        ->get(route('platform.owner.profile.edit'))
        ->assertForbidden();
});

it('allows platform_owner to view the profile page', function () {
    actingAs(createPlatformOwner())
        ->get(route('platform.owner.profile.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Platform/Owner/Profile/Index'));
});

// ===========================================================================
// Profile Update
// ===========================================================================

it('allows platform_owner to update their name and email', function () {
    /** @var User $owner */
    $owner = createPlatformOwner();

    actingAs($owner)
        ->patch(route('platform.owner.profile.update'), [
            'name' => 'New Owner Name',
            'email' => 'newemail@example.com',
        ])
        ->assertRedirect(route('platform.owner.profile.edit'))
        ->assertSessionHas('success');

    $owner->refresh();
    expect($owner->name)->toBe('New Owner Name')
        ->and($owner->email)->toBe('newemail@example.com');
});

it('resets email_verified_at when email is changed', function () {
    /** @var User $owner */
    $owner = createPlatformOwner();
    $owner->update(['email_verified_at' => now()]);

    actingAs($owner)
        ->patch(route('platform.owner.profile.update'), [
            'name' => $owner->name,
            'email' => 'changed@example.com',
        ]);

    expect($owner->fresh()->email_verified_at)->toBeNull();
});

it('does not reset email_verified_at when email stays the same', function () {
    /** @var User $owner */
    $owner = createPlatformOwner();
    $verifiedAt = now()->subHour();
    $owner->update(['email_verified_at' => $verifiedAt]);

    actingAs($owner)
        ->patch(route('platform.owner.profile.update'), [
            'name' => 'Updated Name Only',
            'email' => $owner->email,
        ]);

    expect($owner->fresh()->email_verified_at)->not->toBeNull();
});

it('validates that name is required on profile update', function () {
    actingAs(createPlatformOwner())
        ->patch(route('platform.owner.profile.update'), [
            'name' => '',
            'email' => 'valid@example.com',
        ])
        ->assertSessionHasErrors('name');
});

it('validates that email must be a valid email address', function () {
    actingAs(createPlatformOwner())
        ->patch(route('platform.owner.profile.update'), [
            'name' => 'Owner',
            'email' => 'not-an-email',
        ])
        ->assertSessionHasErrors('email');
});

it('rejects an email already used by another user', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    actingAs(createPlatformOwner())
        ->patch(route('platform.owner.profile.update'), [
            'name' => 'Owner',
            'email' => 'taken@example.com',
        ])
        ->assertSessionHasErrors('email');
});

// ===========================================================================
// Password Update
// ===========================================================================

it('allows platform_owner to update their password', function () {
    /** @var User $owner */
    $owner = createPlatformOwner();

    actingAs($owner)
        ->put(route('platform.owner.profile.password'), [
            'current_password' => 'password',
            'password' => 'NewSecurePass1!',
            'password_confirmation' => 'NewSecurePass1!',
        ])
        ->assertRedirect(route('platform.owner.profile.edit'))
        ->assertSessionHas('success');

    expect(Hash::check('NewSecurePass1!', $owner->fresh()->password))->toBeTrue();
});

it('rejects an incorrect current password', function () {
    actingAs(createPlatformOwner())
        ->put(route('platform.owner.profile.password'), [
            'current_password' => 'wrong-password',
            'password' => 'NewSecurePass1!',
            'password_confirmation' => 'NewSecurePass1!',
        ])
        ->assertSessionHasErrors('current_password');
});

it('rejects when new password and confirmation do not match', function () {
    actingAs(createPlatformOwner())
        ->put(route('platform.owner.profile.password'), [
            'current_password' => 'password',
            'password' => 'NewSecurePass1!',
            'password_confirmation' => 'DifferentPass1!',
        ])
        ->assertSessionHasErrors('password');
});

it('redirects back to the profile page after a successful password update', function () {
    actingAs(createPlatformOwner())
        ->put(route('platform.owner.profile.password'), [
            'current_password' => 'password',
            'password' => 'NewSecurePass1!',
            'password_confirmation' => 'NewSecurePass1!',
        ])
        ->assertRedirect(route('platform.owner.profile.edit'));
});

it('can log in with the new password after updating', function () {
    /** @var User $owner */
    $owner = createPlatformOwner();

    actingAs($owner)
        ->put(route('platform.owner.profile.password'), [
            'current_password' => 'password',
            'password' => 'NewSecurePass1!',
            'password_confirmation' => 'NewSecurePass1!',
        ]);

    expect(Hash::check('NewSecurePass1!', $owner->fresh()->password))->toBeTrue()
        ->and(Hash::check('password', $owner->fresh()->password))->toBeFalse();
});
