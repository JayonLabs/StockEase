<?php

use App\Enums\PaymentMethod;
use App\Enums\ShiftStatus;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\User;
use App\Services\Shift\ShiftService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->service = new ShiftService;
    $this->user = User::factory()->create(['role' => 'cashier']);
});

it('opens a new shift for a user', function () {
    $shift = $this->service->openShift($this->user, 50000);

    expect($shift)->toBeInstanceOf(Shift::class);
    expect($shift->user_id)->toBe($this->user->id);
    expect((float) $shift->starting_cash)->toBe(50000.0);
    expect($shift->status)->toBe(ShiftStatus::Open->value);
    expect($shift->opened_at)->not->toBeNull();
});

it('throws exception when opening shift while one is still open', function () {
    $this->service->openShift($this->user, 50000);

    $this->service->openShift($this->user, 50000);
})->throws(Exception::class, 'Anda masih memiliki shift yang terbuka');

it('closes an open shift', function () {
    $shift = $this->service->openShift($this->user, 50000);

    $closed = $this->service->closeShift($shift, 60000, 'Tutup shift');

    expect($closed->status)->toBe(ShiftStatus::Closed->value);
    expect($closed->closed_at)->not->toBeNull();
    expect((float) $closed->actual_cash)->toBe(60000.0);
    expect($closed->notes)->toBe('Tutup shift');
});

it('throws exception when closing an already closed shift', function () {
    $shift = $this->service->openShift($this->user, 50000);
    $this->service->closeShift($shift, 60000);

    $this->service->closeShift($shift, 60000);
})->throws(Exception::class, 'Shift ini sudah ditutup sebelumnya');

it('calculates expected cash correctly on close', function () {
    $shift = $this->service->openShift($this->user, 50000);

    Sale::factory()->create([
        'shift_id' => $shift->id,
        'status' => 'completed',
        'payment_method' => PaymentMethod::Cash->value,
        'total' => 75000,
    ]);

    $closed = $this->service->closeShift($shift, 130000);

    expect((float) $closed->expected_cash)->toBe(125000.0);
    expect((float) $closed->cash_difference)->toBe(5000.0);
});

it('excludes non-cash sales from expected cash calculation', function () {
    $shift = $this->service->openShift($this->user, 50000);

    Sale::factory()->create([
        'shift_id' => $shift->id,
        'status' => 'completed',
        'payment_method' => PaymentMethod::Qris->value,
        'total' => 30000,
    ]);
    Sale::factory()->create([
        'shift_id' => $shift->id,
        'status' => 'completed',
        'payment_method' => PaymentMethod::Cash->value,
        'total' => 20000,
    ]);

    $closed = $this->service->closeShift($shift, 70000);

    expect((float) $closed->expected_cash)->toBe(70000.0); // 50000 + 20000
});

it('gets paginated shifts', function () {
    Shift::factory()->count(15)->create(['user_id' => $this->user->id]);

    $shifts = $this->service->getPaginatedShifts(null, [], 10);

    expect($shifts->total())->toBe(15);
    expect($shifts->count())->toBe(10);
});

it('filters shifts by search on user name', function () {
    $userA = User::factory()->create(['name' => 'Alice']);
    $userB = User::factory()->create(['name' => 'Bob']);
    Shift::factory()->create(['user_id' => $userA->id]);
    Shift::factory()->create(['user_id' => $userB->id]);

    $shifts = $this->service->getPaginatedShifts(null, ['search' => 'Alice']);

    expect($shifts->total())->toBe(1);
});

it('filters shifts by status', function () {
    Shift::factory()->create(['user_id' => $this->user->id, 'status' => 'open']);
    Shift::factory()->create(['user_id' => $this->user->id, 'status' => 'closed']);

    $shifts = $this->service->getPaginatedShifts(null, ['status' => 'open']);

    expect($shifts->total())->toBe(1);
});

it('filters shifts by date range', function () {
    Shift::factory()->create([
        'user_id' => $this->user->id,
        'opened_at' => now()->subDays(10),
    ]);
    Shift::factory()->create([
        'user_id' => $this->user->id,
        'opened_at' => now(),
    ]);

    $shifts = $this->service->getPaginatedShifts(null, [
        'start' => now()->subDays(2)->toDateString(),
        'end' => now()->toDateString(),
    ]);

    expect($shifts->total())->toBe(1);
});

it('scopes shifts to user when role is cashier', function () {
    $cashier = User::factory()->create(['role' => 'cashier']);
    $otherUser = User::factory()->create(['role' => 'cashier']);
    Shift::factory()->create(['user_id' => $cashier->id]);
    Shift::factory()->create(['user_id' => $otherUser->id]);

    $shifts = $this->service->getPaginatedShifts($cashier, []);

    expect($shifts->total())->toBe(1);
});

it('shows all shifts when user role is admin', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    Shift::factory()->count(3)->create(['user_id' => $this->user->id]);

    $shifts = $this->service->getPaginatedShifts($admin, []);

    expect($shifts->total())->toBe(3);
});

it('gets shift details with loaded relationships', function () {
    $shift = Shift::factory()->create(['user_id' => $this->user->id]);

    $details = $this->service->getShiftDetails($shift);

    expect($details->relationLoaded('user'))->toBeTrue();
    expect($details->relationLoaded('sales'))->toBeTrue();
});

it('checks if user has active shift', function () {
    expect($this->service->hasActiveShift($this->user))->toBeFalse();

    $this->service->openShift($this->user, 50000);

    expect($this->service->hasActiveShift($this->user))->toBeTrue();
});

it('returns null getActiveShift when no open shift exists', function () {
    $active = $this->service->getActiveShift($this->user);

    expect($active)->toBeNull();
});

it('returns active shift for user', function () {
    $shift = $this->service->openShift($this->user, 50000);

    $active = $this->service->getActiveShift($this->user);

    expect($active->id)->toBe($shift->id);
});
