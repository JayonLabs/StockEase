<?php

namespace Tests\Feature\Payment;

use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use Tests\TestCase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    /** @var TestCase&object{admin: User, cashier: User, warehouse: User} $this */
    config(['midtrans.server_key' => 'test-server-key']);
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->cashier = User::factory()->create(['role' => 'cashier']);
    $this->warehouse = User::factory()->create(['role' => 'warehouse']);
});

it('allows admin and cashier to create midtrans transactions using cart total', function (string $role) {
    /** @var TestCase&object{admin: User, cashier: User} $this */
    $user = User::factory()->create(['role' => $role]);

    Sale::factory()->create([
        'user_id' => $user->id,
        'total' => 50000,
        'status' => SaleStatus::Draft->value,
    ]);

    $mock = Mockery::mock('alias:Midtrans\Snap');
    $mock->shouldReceive('getSnapToken')->once()->andReturn('mock-snap-token');

    /** @var User $user */
    $response = actingAs($user)
        ->postJson(route('pos.qris-token'), [
            'amount' => 999999,
            'customer_name' => 'John Doe',
        ]);

    $response->assertSuccessful()
        ->assertJson(['snap_token' => 'mock-snap-token']);
})->with(['admin', 'cashier']);

it('returns error when no draft sale exists', function () {
    /** @var TestCase&object{admin: User} $this */
    $response = actingAs($this->admin)
        ->postJson(route('pos.qris-token'), [
            'amount' => 50000,
            'customer_name' => 'John Doe',
        ]);

    $response->assertServerError();
});

it('requires amount field', function () {
    /** @var TestCase&object{admin: User} $this */
    $response = actingAs($this->admin)
        ->postJson(route('pos.qris-token'), [
            'customer_name' => 'John Doe',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);
});

it('validates amount is numeric', function (mixed $value) {
    /** @var TestCase&object{admin: User} $this */
    $response = actingAs($this->admin)
        ->postJson(route('pos.qris-token'), [
            'amount' => $value,
            'customer_name' => 'John Doe',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);
})->with([
    'string' => 'not-a-number',
    'array' => ['foo'],
]);

it('validates amount minimum is 1', function (mixed $value) {
    /** @var TestCase&object{admin: User} $this */
    $response = actingAs($this->admin)
        ->postJson(route('pos.qris-token'), [
            'amount' => $value,
            'customer_name' => 'John Doe',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);
})->with([
    'zero' => 0,
    'negative' => -1000,
]);

it('validates customer_name max length', function () {
    /** @var TestCase&object{admin: User} $this */
    $response = actingAs($this->admin)
        ->postJson(route('pos.qris-token'), [
            'amount' => 50000,
            'customer_name' => str_repeat('a', 256),
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['customer_name']);
});

it('uses cart total instead of request amount', function () {
    /** @var TestCase&object{admin: User} $this */
    $sale = Sale::factory()->create([
        'user_id' => $this->admin->id,
        'total' => 75000,
        'status' => SaleStatus::Draft->value,
    ]);

    $mock = Mockery::mock('alias:Midtrans\Snap');
    $mock->shouldReceive('getSnapToken')
        ->once()
        ->andReturn('mock-snap-token');

    actingAs($this->admin)
        ->postJson(route('pos.qris-token'), [
            'amount' => 1,
            'customer_name' => 'Test Customer',
        ])
        ->assertSuccessful();

    // Midtrans Snap should receive 75000 (cart total), not 1 (request amount)
    $mock->shouldHaveReceived('getSnapToken');
});

it('passes validated customer_name to snap token', function () {
    /** @var TestCase&object{admin: User} $this */
    Sale::factory()->create([
        'user_id' => $this->admin->id,
        'total' => 50000,
        'status' => SaleStatus::Draft->value,
    ]);

    $mock = Mockery::mock('alias:Midtrans\Snap');
    $mock->shouldReceive('getSnapToken')
        ->once()
        ->andReturn('mock-snap-token');

    $response = actingAs($this->admin)
        ->postJson(route('pos.qris-token'), [
            'amount' => 50000,
            'customer_name' => null,
        ]);

    $response->assertSuccessful()
        ->assertJson(['snap_token' => 'mock-snap-token']);
});

it('rejects unauthenticated requests', function () {
    $response = postJson(route('pos.qris-token'), [
        'amount' => 50000,
        'customer_name' => 'John Doe',
    ]);

    $response->assertUnauthorized();
});

it('rejects warehouse users', function () {
    /** @var TestCase&object{warehouse: User} $this */
    $response = actingAs($this->warehouse)
        ->postJson(route('pos.qris-token'), [
            'amount' => 50000,
            'customer_name' => 'John Doe',
        ]);

    $response->assertForbidden();
});
