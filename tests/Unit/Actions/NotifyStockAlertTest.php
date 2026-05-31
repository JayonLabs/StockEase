<?php

namespace Tests\Unit\Actions;

use App\Actions\NotifyStockAlert;
use App\Models\Product;
use App\Models\User;
use App\Notifications\StockAlertNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

it('notifies users with view_stock_alerts permission when stock is low', function () {
    Notification::fake();

    $userA = User::factory()->create(['role' => 'admin']);
    $userB = User::factory()->create(['role' => 'warehouse']);
    $product = Product::factory()->create(['name' => 'Low Stock Product', 'stock' => 2, 'alert_stock' => 5]);

    (new NotifyStockAlert)->execute($product);

    Notification::assertSentTo($userA, StockAlertNotification::class);
    Notification::assertSentTo($userB, StockAlertNotification::class);
});

it('skips users who already have unread notification for the same product', function () {
    Notification::fake();

    $user = User::factory()->create(['role' => 'admin']);
    $product = Product::factory()->create(['name' => 'Low Stock Product', 'stock' => 2, 'alert_stock' => 5]);

    $user->notifications()->create([
        'id' => Str::uuid(),
        'type' => StockAlertNotification::class,
        'data' => [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'message' => 'Test message',
        ],
        'read_at' => null,
    ]);

    (new NotifyStockAlert)->execute($product);

    Notification::assertNotSentTo($user, StockAlertNotification::class);
});

it('sends notification if the previous one was already read', function () {
    Notification::fake();

    $user = User::factory()->create(['role' => 'admin']);
    $product = Product::factory()->create(['name' => 'Low Stock Product', 'stock' => 2, 'alert_stock' => 5]);

    $user->notifications()->create([
        'id' => Str::uuid(),
        'type' => StockAlertNotification::class,
        'data' => [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'message' => 'Test message',
        ],
        'read_at' => now(),
    ]);

    (new NotifyStockAlert)->execute($product);

    Notification::assertSentTo($user, StockAlertNotification::class);
});

it('uses a single query to check existing unread notifications', function () {
    Notification::fake();

    User::factory()->count(5)->create(['role' => 'admin']);
    $product = Product::factory()->create(['name' => 'Low Stock Product', 'stock' => 2, 'alert_stock' => 5]);

    DB::enableQueryLog();

    (new NotifyStockAlert)->execute($product);

    $queries = DB::getQueryLog();

    DB::disableQueryLog();

    $notificationQueries = array_filter($queries, fn ($q) => str_contains($q['query'], 'notifications'));

    expect($notificationQueries)->toHaveCount(1);
});

it('returns early when no users have view_stock_alerts permission', function () {
    Notification::fake();

    // Create a user with no spatie role — factory assigns random role, so we detach it
    $user = User::factory()->create();
    $user->roles()->detach();

    $product = Product::factory()->create(['name' => 'Low Stock Product', 'stock' => 2, 'alert_stock' => 5]);

    (new NotifyStockAlert)->execute($product);

    Notification::assertNothingSent();
});

it('notifies all eligible users in a single batch', function () {
    Notification::fake();

    $users = User::factory()->count(5)->create(['role' => 'admin']);
    $product = Product::factory()->create(['name' => 'Low Stock Product', 'stock' => 2, 'alert_stock' => 5]);

    (new NotifyStockAlert)->execute($product);

    foreach ($users as $user) {
        Notification::assertSentTo($user, StockAlertNotification::class);
    }
});
