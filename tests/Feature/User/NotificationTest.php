<?php

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);

it('gets all user notifications', function () {
    $user = User::factory()->create();
    $user->notifications()->create([
        'id' => Str::uuid(),
        'type' => 'App\Notifications\StockAlertNotification',
        'data' => [
            'product_id' => 1,
            'product_name' => 'Test Product',
            'message' => 'Stock is low!',
        ],
        'read_at' => null,
    ]);

    $response = $this->actingAs($user)->getJson(route('notifications.index'));

    $response->assertSuccessful()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.data.product_name', 'Test Product');
});

it('transforms notifications to include slug if missing', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['name' => 'Real Product', 'slug' => 'real-product']);

    $user->notifications()->create([
        'id' => Str::uuid(),
        'type' => 'App\Notifications\StockAlertNotification',
        'data' => [
            'product_id' => $product->id,
            'product_name' => 'Real Product',
            'message' => 'Stock is low!',
        ],
        'read_at' => null,
    ]);

    $response = $this->actingAs($user)->getJson(route('notifications.index'));

    $response->assertSuccessful()
        ->assertJsonPath('data.0.data.product_slug', 'real-product');
});

it('can mark a notification as read', function () {
    $user = User::factory()->create();
    $notification = $user->notifications()->create([
        'id' => Str::uuid(),
        'type' => 'TestNotification',
        'data' => ['message' => 'Test'],
        'read_at' => null,
    ]);

    $response = $this->actingAs($user)
        ->postJson(route('notifications.read', $notification->id));

    $response->assertSuccessful();
    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('can mark all notifications as read', function () {
    $user = User::factory()->create();
    $user->notifications()->create([
        'id' => Str::uuid(),
        'type' => 'TestNotification',
        'data' => ['message' => 'Test 1'],
        'read_at' => null,
    ]);
    $user->notifications()->create([
        'id' => Str::uuid(),
        'type' => 'TestNotification',
        'data' => ['message' => 'Test 2'],
        'read_at' => null,
    ]);

    $response = $this->actingAs($user)
        ->postJson(route('notifications.read-all'));

    $response->assertSuccessful();
    expect($user->unreadNotifications()->count())->toBe(0);
});

it('can delete a notification', function () {
    $user = User::factory()->create();
    $notification = $user->notifications()->create([
        'id' => Str::uuid(),
        'type' => 'TestNotification',
        'data' => ['message' => 'Test'],
        'read_at' => null,
    ]);

    $response = $this->actingAs($user)
        ->deleteJson(route('notifications.destroy', $notification->id));

    $response->assertSuccessful();
    expect($user->notifications()->count())->toBe(0);
});

it('loads product slugs with a single query to prevent N+1', function () {
    $user = User::factory()->create();
    $products = Product::factory()->count(5)->create();

    foreach ($products as $product) {
        $user->notifications()->create([
            'id' => Str::uuid(),
            'type' => 'App\Notifications\StockAlertNotification',
            'data' => [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'message' => 'Stock is low!',
            ],
            'read_at' => null,
        ]);
    }

    DB::enableQueryLog();

    $response = $this->actingAs($user)->getJson(route('notifications.index'));

    $response->assertSuccessful();

    $productQueries = collect(DB::getQueryLog())
        ->filter(fn ($q) => str_contains($q['query'], '`products`'));

    DB::disableQueryLog();

    // All 5 product slugs should be resolved in a single batch query
    expect($productQueries)->toHaveCount(1);
});
