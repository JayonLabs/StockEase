<?php

use App\Models\Product;
use App\Models\User;
use App\Notifications\StockAlertNotification;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

// Verify interfaces are correctly implemented

it('implements ShouldQueue interface', function () {
    $notification = new StockAlertNotification(
        Product::factory()->make(['name' => 'Test'])
    );

    expect($notification)->toBeInstanceOf(ShouldQueue::class);
});

it('implements ShouldDispatchAfterCommit interface', function () {
    $notification = new StockAlertNotification(
        Product::factory()->make(['name' => 'Test'])
    );

    expect($notification)->toBeInstanceOf(ShouldDispatchAfterCommit::class);
});

// Verify notification still works with Notification::fake()

it('can be sent via notify method and captured by fake', function () {
    Notification::fake();

    $user = User::factory()->create();
    $product = Product::factory()->create([
        'name' => 'Test Product',
        'stock' => 3,
        'alert_stock' => 5,
    ]);

    $user->notify(new StockAlertNotification($product));

    Notification::assertSentTo($user, StockAlertNotification::class);
});

it('sends notification to multiple users', function () {
    Notification::fake();

    $users = User::factory()->count(5)->create();
    $product = Product::factory()->create([
        'name' => 'Multi User Product',
        'stock' => 1,
        'alert_stock' => 10,
    ]);

    foreach ($users as $user) {
        $user->notify(new StockAlertNotification($product));
    }

    foreach ($users as $user) {
        Notification::assertSentTo($user, StockAlertNotification::class);
    }
});

it('notification includes correct product data', function () {
    $product = Product::factory()->create([
        'name' => 'Data Check Product',
        'stock' => 4,
        'alert_stock' => 10,
    ]);

    $notification = new StockAlertNotification($product);
    $user = User::factory()->create();

    $data = $notification->toArray($user);

    expect($data['product_id'])->toBe($product->id);
    expect($data['product_name'])->toBe('Data Check Product');
    expect($data['current_stock'])->toBe(4);
    expect($data['alert_level'])->toBe(10);
    expect($data['message'])->toContain('Data Check Product');
});

it('has database and broadcast channels', function () {
    $notification = new StockAlertNotification(
        Product::factory()->make(['name' => 'Test'])
    );
    $user = User::factory()->make();

    $channels = $notification->via($user);

    expect($channels)->toContain('database');
    expect($channels)->toContain('broadcast');
});

// Verify notification dispatch respects database transactions
// Note: Notification::fake() captures at dispatch level (before ShouldDispatchAfterCommit
// defers delivery). The actual delivery would be prevented on rollback at the queue level.

it('notification class implements both ShouldQueue and ShouldDispatchAfterCommit', function () {
    $notification = new StockAlertNotification(
        Product::factory()->make(['name' => 'Test'])
    );

    expect($notification)
        ->toBeInstanceOf(ShouldQueue::class)
        ->toBeInstanceOf(ShouldDispatchAfterCommit::class);
});

it('is sent after transaction commits successfully', function () {
    Notification::fake();

    $user = User::factory()->create();
    $product = Product::factory()->create([
        'name' => 'Commit Product',
        'stock' => 1,
        'alert_stock' => 10,
    ]);

    DB::transaction(function () use ($user, $product) {
        $user->notify(new StockAlertNotification($product));
    });

    Notification::assertSentTo($user, StockAlertNotification::class);
});

it('is sent immediately when not inside a transaction', function () {
    Notification::fake();

    $user = User::factory()->create();
    $product = Product::factory()->create([
        'name' => 'No Tx Product',
        'stock' => 0,
        'alert_stock' => 5,
    ]);

    $user->notify(new StockAlertNotification($product));

    Notification::assertSentTo($user, StockAlertNotification::class);
});

// Verify broadcast type is correct

it('has correct broadcast type', function () {
    $notification = new StockAlertNotification(
        Product::factory()->make(['name' => 'Broadcast Test'])
    );

    expect($notification->broadcastType())->toBe('stock.alert');
});

// Verify toDatabase returns same data as toArray

it('toDatabase returns same data as toArray', function () {
    $product = Product::factory()->create(['name' => 'DB Test', 'stock' => 5, 'alert_stock' => 10]);
    $notification = new StockAlertNotification($product);
    $user = User::factory()->create();

    $dbData = $notification->toDatabase($user);
    $arrayData = $notification->toArray($user);

    expect($dbData)->toEqual($arrayData);
});

// Test with zero stock

it('works with zero stock', function () {
    Notification::fake();

    $user = User::factory()->create();
    $product = Product::factory()->create([
        'name' => 'Zero Stock',
        'stock' => 0,
        'alert_stock' => 5,
    ]);

    $user->notify(new StockAlertNotification($product));

    Notification::assertSentTo($user, StockAlertNotification::class);
});

// Test idempotency: multiple dispatches produce distinct notifications

it('creates distinct notifications for each dispatch', function () {
    Notification::fake();

    $user = User::factory()->create();
    $product = Product::factory()->create([
        'name' => 'Distinct Product',
        'stock' => 2,
        'alert_stock' => 10,
    ]);

    $user->notify(new StockAlertNotification($product));
    $user->notify(new StockAlertNotification($product));

    Notification::assertSentTo($user, StockAlertNotification::class, 2);
});
