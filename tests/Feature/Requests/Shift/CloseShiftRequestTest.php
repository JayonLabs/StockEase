<?php

use App\Enums\ShiftStatus;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;

uses(LazilyRefreshDatabase::class);

it('requires authentication', function () {
    $shift = Shift::factory()->create();

    $this->postJson(route('shift.close', $shift), ['actual_cash' => 100000])
        ->assertUnauthorized();
});

it('requires actual_cash field', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);
    $shift = Shift::factory()->create(['user_id' => $user->id, 'status' => ShiftStatus::Open->value]);

    actingAs($user)
        ->postJson(route('shift.close', $shift), [])
        ->assertJsonValidationErrors(['actual_cash']);
});

it('rejects negative actual_cash', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);
    $shift = Shift::factory()->create(['user_id' => $user->id, 'status' => ShiftStatus::Open->value]);

    actingAs($user)
        ->postJson(route('shift.close', $shift), ['actual_cash' => -1])
        ->assertJsonValidationErrors(['actual_cash']);
});

it('accepts zero actual_cash', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);
    $shift = Shift::factory()->create(['user_id' => $user->id, 'status' => ShiftStatus::Open->value]);

    actingAs($user)
        ->postJson(route('shift.close', $shift), ['actual_cash' => 0])
        ->assertRedirect();
});

it('rejects notes exceeding 1000 characters', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);
    $shift = Shift::factory()->create(['user_id' => $user->id, 'status' => ShiftStatus::Open->value]);

    actingAs($user)
        ->postJson(route('shift.close', $shift), [
            'actual_cash' => 100000,
            'notes' => str_repeat('a', 1001),
        ])
        ->assertJsonValidationErrors(['notes']);
});

it('accepts valid close data with notes', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);
    $shift = Shift::factory()->create(['user_id' => $user->id, 'status' => ShiftStatus::Open->value]);

    actingAs($user)
        ->postJson(route('shift.close', $shift), [
            'actual_cash' => 100000,
            'notes' => 'Closed normally',
        ])
        ->assertRedirect();
});
