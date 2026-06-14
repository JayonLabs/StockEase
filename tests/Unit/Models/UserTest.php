<?php

namespace Tests\Unit\Models;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    app()->make(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('determines if user has role via string', function () {
    $user = User::factory()->create(['role' => 'admin']);

    expect($user->hasRole('admin'))->toBeTrue();
    expect($user->hasRole('super_admin'))->toBeFalse();
});

it('determines if user has role via enum', function () {
    $user = User::factory()->create(['role' => 'admin']);

    expect($user->hasRole(Role::Admin))->toBeTrue();
    expect($user->hasRole(Role::SuperAdmin))->toBeFalse();
});

it('determines if user has role via array', function () {
    $user = User::factory()->create(['role' => 'admin']);

    expect($user->hasRole(['admin', 'super_admin']))->toBeTrue();
    expect($user->hasRole(['super_admin', 'manager']))->toBeFalse();
});

it('can have super admin role', function () {
    $user = User::factory()->create();
    $user->syncRoles('super_admin');

    expect($user->hasRole('super_admin'))->toBeTrue();
});

it('belongs to a company', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id, 'role' => 'admin']);

    expect($user->company)->toBeInstanceOf(Company::class);
    expect($user->company->id)->toBe($company->id);
});

it('has hidden attributes', function () {
    $user = User::factory()->create(['role' => 'admin']);

    expect($user->getHidden())->toContain('password');
    expect($user->getHidden())->toContain('remember_token');
});

it('has role accessor via roles relationship', function () {
    $user = User::factory()->create(['role' => 'admin']);

    expect($user->role)->toBe('admin');
});

it('has fillable attributes', function () {
    $user = new User;

    expect($user->getFillable())->toContain('name');
    expect($user->getFillable())->toContain('email');
    expect($user->getFillable())->toContain('password');
    expect($user->getFillable())->toContain('company_id');
});

it('can own a company', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['role' => 'admin']);

    expect($user->ownedCompany)->toBeNull();

    $company->update(['owner_id' => $user->id]);

    expect($user->fresh()->ownedCompany->id)->toBe($company->id);
});

it('has many sales', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['role' => 'cashier', 'company_id' => $company->id]);

    Sale::factory()->count(2)->create(['user_id' => $user->id]);

    expect($user->sales)->toHaveCount(2)
        ->and($user->sales->first())->toBeInstanceOf(Sale::class);
});

it('has many shifts', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['role' => 'cashier', 'company_id' => $company->id]);

    Shift::factory()->count(2)->create(['user_id' => $user->id]);

    expect($user->shifts)->toHaveCount(2)
        ->and($user->shifts->first())->toBeInstanceOf(Shift::class);
});

it('has many subscription invoices', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['role' => 'admin', 'company_id' => $company->id]);

    $subscription = Subscription::factory()->create(['company_id' => $company->id]);
    SubscriptionInvoice::factory()->count(2)->create([
        'user_id' => $user->id,
        'subscription_id' => $subscription->id,
    ]);

    expect($user->subscriptionInvoices)->toHaveCount(2)
        ->and($user->subscriptionInvoices->first())->toBeInstanceOf(SubscriptionInvoice::class);
});
