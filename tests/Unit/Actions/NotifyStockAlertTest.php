<?php

use App\Actions\NotifyStockAlert;
use App\Models\Product;
use App\Models\User;
use App\Notifications\StockAlertNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('notifies all users when stock is low', function () {
    Notification::fake();

    $users = User::factory()->count(3)->create();
    $product = Product::factory()->create(['name' => 'Low Stock Product', 'stock' => 2, 'alert_stock' => 5]);

    (new NotifyStockAlert)->execute($product);

    foreach ($users as $user) {
        Notification::assertSentTo(
            $user,
            StockAlertNotification::class,
            function ($notification, $channels) use ($product, $user) {
                return $notification->toArray($user)['product_id'] === $product->id;
            }
        );
    }
});

it('does not send duplicate unread notifications to the same user', function () {
    Notification::fake();

    $user = User::factory()->create();
    $product = Product::factory()->create(['name' => 'Low Stock Product', 'stock' => 2, 'alert_stock' => 5]);

    // Manually create an unread notification in the database
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

    // Should NOT send another notification because one already exists (unread)
    Notification::assertNotSentTo($user, StockAlertNotification::class);
});

it('sends notification if the previous one was already read', function () {
    Notification::fake();

    $user = User::factory()->create();
    $product = Product::factory()->create(['name' => 'Low Stock Product', 'stock' => 2, 'alert_stock' => 5]);

    // Manually create a READ notification
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

    // Should send a new notification because the previous one was read
    Notification::assertSentTo($user, StockAlertNotification::class);
});
