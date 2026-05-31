<?php

use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

use function Pest\Laravel\actingAs;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    /** @var TestCase&object{admin: User, cashier: User, warehouse: User} $this */
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->cashier = User::factory()->create(['role' => 'cashier']);
    $this->warehouse = User::factory()->create(['role' => 'warehouse']);
});

describe('Midtrans Transaction Access', function () {
    it('allows admin and cashier to view midtrans transactions', function ($role) {
        /** @var TestCase&object{admin: User, cashier: User} $this */
        $user = $this->{$role};
        PaymentTransaction::factory()->count(3)->create();

        $response = actingAs($user)->get(route('midtrans.index'));

        $response->assertSuccessful();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('MidtransTransaction/Index')
            ->has('midtransTransactions.data', 3)
        );
    })->with(['admin', 'cashier']);

    it('denies warehouse to view midtrans transactions', function () {
        /** @var TestCase&object{warehouse: User} $this */
        $response = actingAs($this->warehouse)->get(route('midtrans.index'));
        $response->assertForbidden();
    });
});

describe('Midtrans Transaction Filtering', function () {
    it('filters transactions by date range', function () {
        /** @var TestCase&object{admin: User} $this */
        // Create transactions on different dates
        PaymentTransaction::factory()->create(['created_at' => '2024-04-01 10:00:00']);
        PaymentTransaction::factory()->create(['created_at' => '2024-04-15 10:00:00']);
        PaymentTransaction::factory()->create(['created_at' => '2024-04-30 10:00:00']);
        PaymentTransaction::factory()->create(['created_at' => '2024-05-01 10:00:00']);

        $response = actingAs($this->admin)->get(route('midtrans.index', [
            'start' => '2024-04-01',
            'end' => '2024-04-30',
        ]));

        $response->assertSuccessful();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('MidtransTransaction/Index')
            ->has('midtransTransactions.data', 3)
        );
    });

    it('searches transactions by external_id', function () {
        /** @var TestCase&object{admin: User} $this */
        PaymentTransaction::factory()->create(['external_id' => 'ORDER-12345']);
        PaymentTransaction::factory()->create(['external_id' => 'ORDER-67890']);

        $response = actingAs($this->admin)->get(route('midtrans.index', [
            'search' => '12345',
        ]));

        $response->assertSuccessful();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('MidtransTransaction/Index')
            ->has('midtransTransactions.data', 1)
            ->where('midtransTransactions.data.0.external_id', 'ORDER-12345')
        );
    });

    it('filters by both search and date range simultaneously', function () {
        /** @var TestCase&object{admin: User} $this */
        PaymentTransaction::factory()->create(['external_id' => 'ORDER-AAA', 'created_at' => '2024-04-01 10:00:00']);
        PaymentTransaction::factory()->create(['external_id' => 'ORDER-AAA', 'created_at' => '2024-04-15 10:00:00']);
        PaymentTransaction::factory()->create(['external_id' => 'ORDER-AAA', 'created_at' => '2024-05-01 10:00:00']); // outside date
        PaymentTransaction::factory()->create(['external_id' => 'ORDER-BBB', 'created_at' => '2024-04-10 10:00:00']);

        $response = actingAs($this->admin)->get(route('midtrans.index', [
            'search' => 'AAA',
            'start' => '2024-04-01',
            'end' => '2024-04-30',
        ]));

        $response->assertSuccessful();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('MidtransTransaction/Index')
            ->has('midtransTransactions.data', 2)
        );
    });

    it('passes filters prop back to Vue component', function () {
        /** @var TestCase&object{admin: User} $this */
        $response = actingAs($this->admin)->get(route('midtrans.index', [
            'search' => 'ABC',
            'start' => '2024-04-01',
            'end' => '2024-04-30',
        ]));

        $response->assertSuccessful();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('filters.search', 'ABC')
            ->where('filters.start', '2024-04-01')
            ->where('filters.end', '2024-04-30')
        );
    });

    it('handles pagination', function () {
        /** @var TestCase&object{admin: User} $this */
        PaymentTransaction::factory()->count(15)->create();

        $response = actingAs($this->admin)->get(route('midtrans.index', [
            'per_page' => 5,
        ]));

        $response->assertSuccessful();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('MidtransTransaction/Index')
            ->has('midtransTransactions.data', 5)
            ->where('midtransTransactions.total', 15)
        );
    });
});
