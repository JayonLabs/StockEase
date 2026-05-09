<?php

use App\Models\Sale;
use App\Models\Shift;
use App\Models\User;
use Tests\TestCase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    /** @var TestCase&object{admin:User, cashier:User, warehouse:User} $this */
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->cashier = User::factory()->create(['role' => 'cashier']);
    $this->warehouse = User::factory()->create(['role' => 'warehouse']);
});

// ============================================================
// Authorization
// ============================================================

describe('Authorization', function () {
    it('redirects unauthenticated user from shift index', function () {
        get(route('shift.index'))->assertRedirect(route('login'));
    });

    it('redirects unauthenticated user from shift show', function () {
        $shift = Shift::factory()->create();
        get(route('shift.show', $shift))->assertRedirect(route('login'));
    });

    it('redirects unauthenticated user from shift store', function () {
        post(route('shift.store'), ['starting_cash' => 100000])
            ->assertRedirect(route('login'));
    });

    it('redirects unauthenticated user from shift close', function () {
        $shift = Shift::factory()->create();
        post(route('shift.close', $shift), ['actual_cash' => 500000])
            ->assertRedirect(route('login'));
    });

    it('forbids warehouse from shift index', function () {
        /** @var TestCase&object{warehouse:User} $this */
        actingAs($this->warehouse)
            ->get(route('shift.index'))
            ->assertForbidden();
    });

    it('forbids warehouse from shift show', function () {
        /** @var TestCase&object{warehouse:User} $this */
        $shift = Shift::factory()->create();

        actingAs($this->warehouse)
            ->get(route('shift.show', $shift))
            ->assertForbidden();
    });

    it('forbids warehouse from shift store', function () {
        /** @var TestCase&object{warehouse:User} $this */
        actingAs($this->warehouse)
            ->post(route('shift.store'), ['starting_cash' => 100000])
            ->assertForbidden();
    });

    it('forbids warehouse from shift close', function () {
        /** @var TestCase&object{warehouse:User} $this */
        $shift = Shift::factory()->create();

        actingAs($this->warehouse)
            ->post(route('shift.close', $shift), ['actual_cash' => 500000])
            ->assertForbidden();
    });

    it('allows admin and cashier to access shift index', function (string $role) {
        /** @var TestCase&object{admin:User, cashier:User} $this */
        $user = $this->{$role};

        actingAs($user)
            ->get(route('shift.index'))
            ->assertSuccessful();
    })->with(['admin', 'cashier']);

    it('allows admin and cashier to access shift show', function (string $role) {
        /** @var TestCase&object{admin:User, cashier:User} $this */
        $shift = Shift::factory()->create();
        $user = $this->{$role};

        actingAs($user)
            ->get(route('shift.show', $shift))
            ->assertSuccessful();
    })->with(['admin', 'cashier']);
});

// ============================================================
// Index
// ============================================================

describe('Index', function () {
    it('renders the Shift/Index component', function () {
        /** @var TestCase&object{admin:User} $this */
        actingAs($this->admin)
            ->get(route('shift.index'))
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->component('Shift/Index'));
    });

    it('passes shifts prop with paginator structure', function () {
        /** @var TestCase&object{admin:User} $this */
        actingAs($this->admin)
            ->get(route('shift.index'))
            ->assertInertia(
                fn ($page) => $page
                    ->has('shifts.data')
                    ->has('shifts.current_page')
                    ->has('shifts.per_page')
                    ->has('shifts.total')
            );
    });

    it('passes hasActiveShift prop as boolean', function () {
        /** @var TestCase&object{admin:User} $this */
        actingAs($this->admin)
            ->get(route('shift.index'))
            ->assertInertia(
                fn ($page) => $page
                    ->where('hasActiveShift', false)
            );
    });

    it('shows hasActiveShift true when user has open shift', function () {
        /** @var TestCase&object{cashier:User} $this */
        Shift::factory()->create([
            'user_id' => $this->cashier->id,
            'status' => 'open',
        ]);

        actingAs($this->cashier)
            ->get(route('shift.index'))
            ->assertInertia(
                fn ($page) => $page
                    ->where('hasActiveShift', true)
            );
    });

    it('paginates with default 10 per page', function () {
        /** @var TestCase&object{admin:User} $this */
        Shift::factory()->count(12)->create();

        actingAs($this->admin)
            ->get(route('shift.index'))
            ->assertInertia(
                fn ($page) => $page
                    ->has('shifts.data', 10)
                    ->where('shifts.total', 12)
            );
    });

    it('respects per_page query parameter', function () {
        /** @var TestCase&object{admin:User} $this */
        Shift::factory()->count(10)->create();

        actingAs($this->admin)
            ->get(route('shift.index', ['per_page' => 5]))
            ->assertInertia(fn ($page) => $page->has('shifts.data', 5));
    });

    it('cashier only sees own shifts', function () {
        /** @var TestCase&object{cashier:User} $this */
        $otherCashier = User::factory()->create(['role' => 'cashier']);

        Shift::factory()->create(['user_id' => $this->cashier->id]);
        Shift::factory()->create(['user_id' => $otherCashier->id]);
        Shift::factory()->create(['user_id' => $this->cashier->id]);

        actingAs($this->cashier)
            ->get(route('shift.index'))
            ->assertInertia(fn ($page) => $page->has('shifts.data', 2));
    });

    it('admin sees all shifts', function () {
        /** @var TestCase&object{admin:User} $this */
        $cashierA = User::factory()->create(['role' => 'cashier']);
        $cashierB = User::factory()->create(['role' => 'cashier']);

        Shift::factory()->create(['user_id' => $cashierA->id]);
        Shift::factory()->create(['user_id' => $cashierB->id]);

        actingAs($this->admin)
            ->get(route('shift.index'))
            ->assertInertia(fn ($page) => $page->has('shifts.data', 2));
    });

    it('filters by status', function () {
        /** @var TestCase&object{admin:User} $this */
        Shift::factory()->closed()->count(3)->create();
        Shift::factory()->create(['status' => 'open']);

        actingAs($this->admin)
            ->get(route('shift.index', ['status' => 'open']))
            ->assertInertia(fn ($page) => $page->has('shifts.data', 1));
    });

    it('filters by date range', function () {
        /** @var TestCase&object{admin:User} $this */
        Shift::factory()->create(['opened_at' => '2024-04-01 08:00:00']);
        Shift::factory()->create(['opened_at' => '2024-04-15 08:00:00']);
        Shift::factory()->create(['opened_at' => '2024-05-01 08:00:00']);

        actingAs($this->admin)
            ->get(route('shift.index', ['start' => '2024-04-01', 'end' => '2024-04-30']))
            ->assertInertia(fn ($page) => $page->has('shifts.data', 2));
    });

    it('orders shifts by created_at descending', function () {
        /** @var TestCase&object{admin:User} $this */
        $older = Shift::factory()->create(['created_at' => now()->subDays(2)]);
        $newer = Shift::factory()->create(['created_at' => now()]);

        actingAs($this->admin)
            ->get(route('shift.index'))
            ->assertInertia(
                fn ($page) => $page
                    ->where('shifts.data.0.id', $newer->id)
                    ->where('shifts.data.1.id', $older->id)
            );
    });
});

// ============================================================
// Index — Filtering: default status
// ============================================================

describe('Default status filter', function () {
    it('defaults to showing only open shifts', function () {
        /** @var TestCase&object{admin:User} $this */
        Shift::factory()->create(['status' => 'open']);
        Shift::factory()->create(['status' => 'open']);
        Shift::factory()->closed()->count(3)->create();

        actingAs($this->admin)
            ->get(route('shift.index'))
            ->assertInertia(fn ($page) => $page->has('shifts.data', 2));
    });

    it('shows only closed shifts when status=closed', function () {
        /** @var TestCase&object{admin:User} $this */
        Shift::factory()->create(['status' => 'open']);
        Shift::factory()->closed()->count(4)->create();

        actingAs($this->admin)
            ->get(route('shift.index', ['status' => 'closed']))
            ->assertInertia(fn ($page) => $page->has('shifts.data', 4));
    });

    it('shows all shifts when status=all', function () {
        /** @var TestCase&object{admin:User} $this */
        Shift::factory()->create(['status' => 'open']);
        Shift::factory()->create(['status' => 'open']);
        Shift::factory()->closed()->count(3)->create();

        actingAs($this->admin)
            ->get(route('shift.index', ['status' => 'all']))
            ->assertInertia(fn ($page) => $page->has('shifts.data', 5));
    });

    it('passes filters prop with defaults', function () {
        /** @var TestCase&object{admin:User} $this */
        actingAs($this->admin)
            ->get(route('shift.index'))
            ->assertInertia(
                fn ($page) => $page
                    ->has('filters')
                    ->where('filters.status', 'open')
                    ->where('filters.start', '')
                    ->where('filters.end', '')
            );
    });

    it('passes filters prop with applied values', function () {
        /** @var TestCase&object{admin:User} $this */
        actingAs($this->admin)
            ->get(route('shift.index', [
                'status' => 'closed',
                'start' => '2024-04-01',
                'end' => '2024-04-30',
            ]))
            ->assertInertia(
                fn ($page) => $page
                    ->where('filters.status', 'closed')
                    ->where('filters.start', '2024-04-01')
                    ->where('filters.end', '2024-04-30')
            );
    });
});

// ============================================================
// Index — Filtering: date range
// ============================================================

describe('Date range filter', function () {
    it('filters by date range with default status open', function () {
        /** @var TestCase&object{admin:User} $this */
        Shift::factory()->create(['opened_at' => '2024-04-01 08:00:00', 'status' => 'open']);
        Shift::factory()->create(['opened_at' => '2024-04-15 08:00:00', 'status' => 'open']);
        Shift::factory()->create(['opened_at' => '2024-04-30 08:00:00', 'status' => 'open']);
        Shift::factory()->create(['opened_at' => '2024-05-01 08:00:00', 'status' => 'open']);

        actingAs($this->admin)
            ->get(route('shift.index', ['start' => '2024-04-01', 'end' => '2024-04-30']))
            ->assertInertia(fn ($page) => $page->has('shifts.data', 3));
    });

    it('combines date range with status filter', function () {
        /** @var TestCase&object{admin:User} $this */
        Shift::factory()->closed()->create(['opened_at' => '2024-04-01 08:00:00']);
        Shift::factory()->closed()->create(['opened_at' => '2024-04-15 08:00:00']);
        Shift::factory()->create(['opened_at' => '2024-04-10 08:00:00', 'status' => 'open']);
        Shift::factory()->closed()->create(['opened_at' => '2024-05-01 08:00:00']);

        actingAs($this->admin)
            ->get(route('shift.index', [
                'status' => 'closed',
                'start' => '2024-04-01',
                'end' => '2024-04-30',
            ]))
            ->assertInertia(fn ($page) => $page->has('shifts.data', 2));
    });

    it('combines date range with status=all', function () {
        /** @var TestCase&object{admin:User} $this */
        Shift::factory()->closed()->create(['opened_at' => '2024-04-01 08:00:00']);
        Shift::factory()->create(['opened_at' => '2024-04-10 08:00:00', 'status' => 'open']);
        Shift::factory()->create(['opened_at' => '2024-05-10 08:00:00', 'status' => 'open']);

        actingAs($this->admin)
            ->get(route('shift.index', [
                'status' => 'all',
                'start' => '2024-04-01',
                'end' => '2024-04-30',
            ]))
            ->assertInertia(fn ($page) => $page->has('shifts.data', 2));
    });

    it('ignores date filter when only start is provided', function () {
        /** @var TestCase&object{admin:User} $this */
        Shift::factory()->create(['opened_at' => '2024-03-15 08:00:00', 'status' => 'open']);
        Shift::factory()->create(['opened_at' => '2024-04-15 08:00:00', 'status' => 'open']);

        actingAs($this->admin)
            ->get(route('shift.index', ['start' => '2024-04-01']))
            ->assertInertia(fn ($page) => $page->has('shifts.data', 2));
    });

    it('ignores date filter when only end is provided', function () {
        /** @var TestCase&object{admin:User} $this */
        Shift::factory()->create(['opened_at' => '2024-03-15 08:00:00', 'status' => 'open']);
        Shift::factory()->create(['opened_at' => '2024-04-15 08:00:00', 'status' => 'open']);

        actingAs($this->admin)
            ->get(route('shift.index', ['end' => '2024-04-30']))
            ->assertInertia(fn ($page) => $page->has('shifts.data', 2));
    });
});

// ============================================================
// Index — Filtering: search
// ============================================================

describe('Search filter', function () {
    it('searches by cashier name', function () {
        /** @var TestCase&object{admin:User} $this */
        $cashierA = User::factory()->create(['name' => 'Budi Kasir', 'role' => 'cashier']);
        $cashierB = User::factory()->create(['name' => 'Rina Kasir', 'role' => 'cashier']);

        Shift::factory()->create(['user_id' => $cashierA->id, 'status' => 'open']);
        Shift::factory()->create(['user_id' => $cashierB->id, 'status' => 'open']);

        actingAs($this->admin)
            ->get(route('shift.index', ['search' => 'Budi', 'status' => 'all']))
            ->assertInertia(fn ($page) => $page->has('shifts.data', 1));
    });

    it('returns empty when search has no match', function () {
        /** @var TestCase&object{admin:User} $this */
        Shift::factory()->create(['status' => 'open']);

        actingAs($this->admin)
            ->get(route('shift.index', ['search' => 'xyznonexistent']))
            ->assertInertia(fn ($page) => $page->has('shifts.data', 0));
    });

    it('combines search with status filter', function () {
        /** @var TestCase&object{admin:User} $this */
        $cashier = User::factory()->create(['name' => 'Dewa Kasir', 'role' => 'cashier']);

        Shift::factory()->create(['user_id' => $cashier->id, 'status' => 'open']);
        Shift::factory()->closed()->create(['user_id' => $cashier->id]);

        actingAs($this->admin)
            ->get(route('shift.index', ['search' => 'Dewa', 'status' => 'closed']))
            ->assertInertia(fn ($page) => $page->has('shifts.data', 1));
    });
});

// ============================================================
// Store — Open Shift
// ============================================================

describe('Store — Open Shift', function () {
    it('opens a new shift successfully', function () {
        /** @var TestCase&object{cashier:User} $this */
        actingAs($this->cashier)
            ->post(route('shift.store'), ['starting_cash' => 200000])
            ->assertRedirect(route('shift.index'))
            ->assertSessionHas('success', 'Shift berhasil dibuka.');

        assertDatabaseHas('shifts', [
            'user_id' => $this->cashier->id,
            'starting_cash' => 200000,
            'status' => 'open',
        ]);
    });

    it('opens shift for admin', function () {
        /** @var TestCase&object{admin:User} $this */
        actingAs($this->admin)
            ->post(route('shift.store'), ['starting_cash' => 500000])
            ->assertRedirect(route('shift.index'))
            ->assertSessionHas('success', 'Shift berhasil dibuka.');
    });

    it('cannot open a new shift if one is already open', function () {
        /** @var TestCase&object{cashier:User} $this */
        Shift::factory()->create([
            'user_id' => $this->cashier->id,
            'status' => 'open',
        ]);

        actingAs($this->cashier)
            ->post(route('shift.store'), ['starting_cash' => 300000])
            ->assertRedirect()
            ->assertSessionHas('error');
    });

    it('validates starting_cash is required', function () {
        /** @var TestCase&object{cashier:User} $this */
        actingAs($this->cashier)
            ->post(route('shift.store'), [])
            ->assertSessionHasErrors('starting_cash');
    });

    it('validates starting_cash must be numeric', function () {
        /** @var TestCase&object{cashier:User} $this */
        actingAs($this->cashier)
            ->post(route('shift.store'), ['starting_cash' => 'abc'])
            ->assertSessionHasErrors('starting_cash');
    });

    it('validates starting_cash must be at least 0', function () {
        /** @var TestCase&object{cashier:User} $this */
        actingAs($this->cashier)
            ->post(route('shift.store'), ['starting_cash' => -1000])
            ->assertSessionHasErrors('starting_cash');
    });
});

// ============================================================
// Show
// ============================================================

describe('Show', function () {
    it('renders the Shift/Show component', function () {
        /** @var TestCase&object{admin:User} $this */
        $shift = Shift::factory()->create();

        actingAs($this->admin)
            ->get(route('shift.show', $shift))
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->component('Shift/Show'));
    });

    it('passes shift prop with expected keys', function () {
        /** @var TestCase&object{admin:User} $this */
        $shift = Shift::factory()->create();

        actingAs($this->admin)
            ->get(route('shift.show', $shift))
            ->assertInertia(
                fn ($page) => $page
                    ->has('shift.id')
                    ->has('shift.starting_cash')
                    ->has('shift.status')
                    ->has('shift.opened_at')
                    ->has('shift.user')
            );
    });

    it('loads shift with user relationship', function () {
        /** @var TestCase&object{admin:User, cashier:User} $this */
        $shift = Shift::factory()->create(['user_id' => $this->cashier->id]);

        actingAs($this->admin)
            ->get(route('shift.show', $shift))
            ->assertInertia(
                fn ($page) => $page
                    ->has('shift.user')
                    ->where('shift.user.name', $this->cashier->name)
            );
    });

    it('loads shift with completed sales', function () {
        /** @var TestCase&object{admin:User} $this */
        $shift = Shift::factory()->create();
        Sale::factory()->create([
            'shift_id' => $shift->id,
            'status' => 'completed',
        ]);
        Sale::factory()->create([
            'shift_id' => $shift->id,
            'status' => 'draft',
        ]);

        actingAs($this->admin)
            ->get(route('shift.show', $shift))
            ->assertInertia(
                fn ($page) => $page->has('shift.sales', 1)
            );
    });

    it('returns 404 for non-existent shift', function () {
        /** @var TestCase&object{admin:User} $this */
        actingAs($this->admin)
            ->get(route('shift.show', 999999))
            ->assertNotFound();
    });
});

// ============================================================
// Close — Close Shift
// ============================================================

describe('Close — Close Shift', function () {
    it('closes an open shift successfully', function () {
        /** @var TestCase&object{cashier:User} $this */
        $shift = Shift::factory()->create([
            'user_id' => $this->cashier->id,
            'starting_cash' => 100000,
            'status' => 'open',
        ]);

        actingAs($this->cashier)
            ->post(route('shift.close', $shift), [
                'actual_cash' => 500000,
                'notes' => 'Semua sesuai',
            ])
            ->assertRedirect(route('shift.show', $shift))
            ->assertSessionHas('success', 'Shift berhasil ditutup.');

        assertDatabaseHas('shifts', [
            'id' => $shift->id,
            'status' => 'closed',
            'actual_cash' => 500000,
            'notes' => 'Semua sesuai',
        ]);
    });

    it('calculates expected_cash correctly when closing', function () {
        /** @var TestCase&object{cashier:User} $this */
        $shift = Shift::factory()->create([
            'user_id' => $this->cashier->id,
            'starting_cash' => 100000,
            'status' => 'open',
        ]);

        Sale::factory()->create([
            'shift_id' => $shift->id,
            'user_id' => $this->cashier->id,
            'status' => 'completed',
            'payment_method' => 'cash',
            'total' => 50000,
        ]);

        Sale::factory()->create([
            'shift_id' => $shift->id,
            'user_id' => $this->cashier->id,
            'status' => 'completed',
            'payment_method' => 'cash',
            'total' => 75000,
        ]);

        actingAs($this->cashier)
            ->post(route('shift.close', $shift), ['actual_cash' => 250000]);

        assertDatabaseHas('shifts', [
            'id' => $shift->id,
            'status' => 'closed',
            'expected_cash' => 225000, // 100000 + 50000 + 75000
            'actual_cash' => 250000,
            'cash_difference' => 25000,  // 250000 - 225000
        ]);
    });

    it('excludes non-cash and non-completed sales from expected_cash', function () {
        /** @var TestCase&object{cashier:User} $this */
        $shift = Shift::factory()->create([
            'user_id' => $this->cashier->id,
            'starting_cash' => 100000,
            'status' => 'open',
        ]);

        Sale::factory()->create([
            'shift_id' => $shift->id,
            'status' => 'completed',
            'payment_method' => 'qris',
            'total' => 50000,
        ]);

        Sale::factory()->create([
            'shift_id' => $shift->id,
            'status' => 'pending',
            'payment_method' => 'cash',
            'total' => 30000,
        ]);

        actingAs($this->cashier)
            ->post(route('shift.close', $shift), ['actual_cash' => 100000]);

        assertDatabaseHas('shifts', [
            'id' => $shift->id,
            'status' => 'closed',
            'expected_cash' => 100000, // only starting cash — qris and pending excluded
            'actual_cash' => 100000,
            'cash_difference' => 0,
        ]);
    });

    it('handles negative cash_difference (shortage)', function () {
        /** @var TestCase&object{cashier:User} $this */
        $shift = Shift::factory()->create([
            'user_id' => $this->cashier->id,
            'starting_cash' => 200000,
            'status' => 'open',
        ]);

        Sale::factory()->create([
            'shift_id' => $shift->id,
            'status' => 'completed',
            'payment_method' => 'cash',
            'total' => 100000,
        ]);

        actingAs($this->cashier)
            ->post(route('shift.close', $shift), ['actual_cash' => 250000]);

        assertDatabaseHas('shifts', [
            'id' => $shift->id,
            'cash_difference' => -50000, // 250000 - 300000
        ]);
    });

    it('cannot close an already closed shift', function () {
        /** @var TestCase&object{cashier:User} $this */
        $shift = Shift::factory()->closed()->create([
            'user_id' => $this->cashier->id,
        ]);

        actingAs($this->cashier)
            ->post(route('shift.close', $shift), ['actual_cash' => 999999])
            ->assertRedirect()
            ->assertSessionHas('error');
    });

    it('validates actual_cash is required', function () {
        /** @var TestCase&object{cashier:User} $this */
        $shift = Shift::factory()->create([
            'user_id' => $this->cashier->id,
            'status' => 'open',
        ]);

        actingAs($this->cashier)
            ->post(route('shift.close', $shift), [])
            ->assertSessionHasErrors('actual_cash');
    });

    it('validates actual_cash must be numeric', function () {
        /** @var TestCase&object{cashier:User} $this */
        $shift = Shift::factory()->create([
            'user_id' => $this->cashier->id,
            'status' => 'open',
        ]);

        actingAs($this->cashier)
            ->post(route('shift.close', $shift), ['actual_cash' => 'abc'])
            ->assertSessionHasErrors('actual_cash');
    });

    it('validates actual_cash must be at least 0', function () {
        /** @var TestCase&object{cashier:User} $this */
        $shift = Shift::factory()->create([
            'user_id' => $this->cashier->id,
            'status' => 'open',
        ]);

        actingAs($this->cashier)
            ->post(route('shift.close', $shift), ['actual_cash' => -100])
            ->assertSessionHasErrors('actual_cash');
    });

    it('stores notes as null when not provided', function () {
        /** @var TestCase&object{cashier:User} $this */
        $shift = Shift::factory()->create([
            'user_id' => $this->cashier->id,
            'starting_cash' => 100000,
            'status' => 'open',
        ]);

        actingAs($this->cashier)
            ->post(route('shift.close', $shift), ['actual_cash' => 200000]);

        assertDatabaseHas('shifts', [
            'id' => $shift->id,
            'status' => 'closed',
            'notes' => null,
        ]);
    });

    it('closing shift sets closed_at timestamp', function () {
        /** @var TestCase&object{cashier:User} $this */
        $shift = Shift::factory()->create([
            'user_id' => $this->cashier->id,
            'status' => 'open',
        ]);

        actingAs($this->cashier)
            ->post(route('shift.close', $shift), ['actual_cash' => 100000]);

        $shift->refresh();

        expect($shift->closed_at)->not->toBeNull();
    });
});
