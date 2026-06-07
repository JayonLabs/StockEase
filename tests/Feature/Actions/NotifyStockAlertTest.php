<?php

use App\Actions\NotifyStockAlert;
use App\Models\Product;
use App\Models\User;
use App\Notifications\StockAlertNotification;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

use function Pest\Laravel\seed;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    seed(RoleAndPermissionSeeder::class);
});

it('notifies all users with view_stock_alerts permission', function () {
    Notification::fake();

    $product = Product::factory()->create(['stock' => 5, 'alert_stock' => 10]);

    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $userA->givePermissionTo('view_stock_alerts');
    $userB->givePermissionTo('view_stock_alerts');

    (new NotifyStockAlert)->execute($product);

    Notification::assertSentTo($userA, StockAlertNotification::class);
    Notification::assertSentTo($userB, StockAlertNotification::class);
});

it('does not notify users without view_stock_alerts permission', function () {
    Notification::fake();

    $product = Product::factory()->create(['stock' => 5, 'alert_stock' => 10]);

    $user = User::factory()->create();
    $user->syncRoles([]);
    $user->syncPermissions([]);

    (new NotifyStockAlert)->execute($product);

    Notification::assertNothingSent();
});

it('does nothing when no users have the permission', function () {
    Notification::fake();

    $product = Product::factory()->create(['stock' => 5, 'alert_stock' => 10]);

    User::factory()->count(3)->create()->each(fn ($u) => $u->syncRoles([])->syncPermissions([]));

    (new NotifyStockAlert)->execute($product);

    Notification::assertNothingSent();
});

it('skips users who already have an unread stock alert for the same product', function () {
    Notification::fake();

    $product = Product::factory()->create(['stock' => 5, 'alert_stock' => 10]);

    $user = User::factory()->create();
    $user->givePermissionTo('view_stock_alerts');

    // Simulate an existing unread notification in the database
    DB::table('notifications')->insert([
        'id' => Str::uuid(),
        'type' => StockAlertNotification::class,
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => json_encode(['product_id' => $product->id]),
        'read_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    (new NotifyStockAlert)->execute($product);

    Notification::assertNothingSent();
});

it('notifies users who have a read (not unread) alert for the same product', function () {
    Notification::fake();

    $product = Product::factory()->create(['stock' => 5, 'alert_stock' => 10]);

    $user = User::factory()->create();
    $user->givePermissionTo('view_stock_alerts');

    // Simulate an already-read notification — should NOT block re-notification
    DB::table('notifications')->insert([
        'id' => Str::uuid(),
        'type' => StockAlertNotification::class,
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => json_encode(['product_id' => $product->id]),
        'read_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    (new NotifyStockAlert)->execute($product);

    Notification::assertSentTo($user, StockAlertNotification::class);
});

it('notifies users who have an unread alert for a DIFFERENT product', function () {
    Notification::fake();

    $product = Product::factory()->create(['stock' => 5, 'alert_stock' => 10]);
    $otherProduct = Product::factory()->create(['stock' => 1, 'alert_stock' => 5]);

    $user = User::factory()->create();
    $user->givePermissionTo('view_stock_alerts');

    DB::table('notifications')->insert([
        'id' => Str::uuid(),
        'type' => StockAlertNotification::class,
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => json_encode(['product_id' => $otherProduct->id]),
        'read_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    (new NotifyStockAlert)->execute($product);

    Notification::assertSentTo($user, StockAlertNotification::class);
});

it('sends notification with correct product data', function () {
    Notification::fake();

    $product = Product::factory()->create(['stock' => 3, 'alert_stock' => 10]);

    $user = User::factory()->create();
    $user->givePermissionTo('view_stock_alerts');

    (new NotifyStockAlert)->execute($product);

    Notification::assertSentTo(
        $user,
        StockAlertNotification::class,
        fn (StockAlertNotification $notification) => $notification->toArray($user)['product_id'] === $product->id
    );
});

it('selectively notifies only users without an existing unread alert', function () {
    Notification::fake();

    $product = Product::factory()->create(['stock' => 5, 'alert_stock' => 10]);

    $alreadyNotified = User::factory()->create();
    $newUser = User::factory()->create();
    $alreadyNotified->givePermissionTo('view_stock_alerts');
    $newUser->givePermissionTo('view_stock_alerts');

    DB::table('notifications')->insert([
        'id' => Str::uuid(),
        'type' => StockAlertNotification::class,
        'notifiable_type' => User::class,
        'notifiable_id' => $alreadyNotified->id,
        'data' => json_encode(['product_id' => $product->id]),
        'read_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    (new NotifyStockAlert)->execute($product);

    Notification::assertNotSentTo($alreadyNotified, StockAlertNotification::class);
    Notification::assertSentTo($newUser, StockAlertNotification::class);
});
