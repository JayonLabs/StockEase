<?php

use App\Models\Category;
use App\Models\PaymentTransaction;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\PurchaseItem;
use App\Models\SaleEmail;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\Shift;
use App\Models\StockAdjustment;
use App\Models\StockLog;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertSoftDeleted;

it('shows trash index page with empty state', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)
        ->get(route('trash.index'))
        ->assertSuccessful();
});

it('shows trashed items on index page', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::factory()->create(['name' => 'Deleted Cat']);
    $category->delete();

    /** @var User $admin */
    actingAs($admin)
        ->get(route('trash.index'))
        ->assertSuccessful();
});

it('denies non-admin from trash page', function () {
    $cashier = User::factory()->create(['role' => 'cashier']);

    /** @var User $cashier */
    actingAs($cashier)
        ->get(route('trash.index'))
        ->assertForbidden();
});

it('restores a soft-deleted model', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::factory()->create(['name' => 'Restore Me']);
    $category->delete();

    assertSoftDeleted('categories', ['id' => $category->id]);

    /** @var User $admin */
    actingAs($admin)
        ->post(route('trash.restore'), [
            'type' => 'Category',
            'id' => $category->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(Category::find($category->id))->not->toBeNull();
    expect($category->fresh()->trashed())->toBeFalse();
});

it('force deletes a soft-deleted model', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::factory()->create();
    $category->delete();

    assertSoftDeleted('categories', ['id' => $category->id]);

    /** @var User $admin */
    actingAs($admin)
        ->delete(route('trash.force-destroy'), [
            'type' => 'Category',
            'id' => $category->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(Category::withTrashed()->where('id', $category->id)->exists())->toBeFalse();
});

it('returns 404 for invalid model type on restore', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    /** @var User $admin */
    actingAs($admin)
        ->post(route('trash.restore'), [
            'type' => 'InvalidModel',
            'id' => 1,
        ])
        ->assertNotFound();
});

it('returns 404 for invalid model type on force delete', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    /** @var User $admin */
    actingAs($admin)
        ->delete(route('trash.force-destroy'), [
            'type' => 'InvalidModel',
            'id' => 1,
        ])
        ->assertNotFound();
});

it('returns 404 when restoring non-existent trashed model', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    /** @var User $admin */
    actingAs($admin)
        ->post(route('trash.restore'), [
            'type' => 'Category',
            'id' => 99999,
        ])
        ->assertNotFound();
});

it('validates type and id are required for restore', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    /** @var User $admin */
    actingAs($admin)
        ->post(route('trash.restore'), [])
        ->assertSessionHasErrors(['type', 'id']);
});

it('validates type and id are required for force delete', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    /** @var User $admin */
    actingAs($admin)
        ->delete(route('trash.force-destroy'), [])
        ->assertSessionHasErrors(['type', 'id']);
});

it('denies non-admin from restoring', function () {
    $cashier = User::factory()->create(['role' => 'cashier']);

    /** @var User $cashier */
    actingAs($cashier)
        ->post(route('trash.restore'), [
            'type' => 'Category',
            'id' => 1,
        ])
        ->assertForbidden();
});

it('denies non-admin from force deleting', function () {
    $cashier = User::factory()->create(['role' => 'cashier']);

    /** @var User $cashier */
    actingAs($cashier)
        ->delete(route('trash.force-destroy'), [
            'type' => 'Category',
            'id' => 1,
        ])
        ->assertForbidden();
});

it('searches trashed items', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    Category::factory()->create(['name' => 'Searchable'])->delete();
    Category::factory()->create(['name' => 'Other'])->delete();

    /** @var User $admin */
    actingAs($admin)
        ->get(route('trash.index', ['search' => 'Searchable']))
        ->assertSuccessful();
});

it('can restore a soft-deleted product', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $product = Product::factory()->create(['name' => 'Deleted Product']);
    $product->delete();

    /** @var User $admin */
    actingAs($admin)
        ->post(route('trash.restore'), [
            'type' => 'Product',
            'id' => $product->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(Product::find($product->id))->not->toBeNull();
});

it('shows trashed item detail page', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::factory()->create(['name' => 'Detail Cat']);
    $category->delete();

    /** @var User $admin */
    actingAs($admin)
        ->get(route('trash.show', ['type' => 'Category', 'id' => $category->id]))
        ->assertSuccessful();
});

it('returns 404 when showing trashed item with invalid type', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    /** @var User $admin */
    actingAs($admin)
        ->get(route('trash.show', ['type' => 'InvalidModel', 'id' => 1]))
        ->assertNotFound();
});

it('returns 404 when showing non-existent trashed item', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    /** @var User $admin */
    actingAs($admin)
        ->get(route('trash.show', ['type' => 'Category', 'id' => 99999]))
        ->assertNotFound();
});

it('denies non-admin from viewing trashed item detail', function () {
    $cashier = User::factory()->create(['role' => 'cashier']);
    $category = Category::factory()->create();
    $category->delete();

    /** @var User $cashier */
    actingAs($cashier)
        ->get(route('trash.show', ['type' => 'Category', 'id' => $category->id]))
        ->assertForbidden();
});

it('shows trashed product detail with attributes', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $product = Product::factory()->create([
        'name' => 'Show Product',
        'barcode' => 'CODE123',
        'stock' => 10,
    ]);
    $product->delete();

    /** @var User $admin */
    actingAs($admin)
        ->get(route('trash.show', ['type' => 'Product', 'id' => $product->id]))
        ->assertSuccessful();
});

// --- Restore new model types ---

it('restores a soft-deleted warehouse', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $warehouse = Warehouse::factory()->create(['name' => 'Gudang Test']);
    $warehouse->delete();

    actingAs($admin)
        ->post(route('trash.restore'), [
            'type' => 'Warehouse',
            'id' => $warehouse->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(Warehouse::find($warehouse->id))->not->toBeNull();
});

it('restores a soft-deleted shift', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $shift = Shift::factory()->create();
    $shift->delete();

    actingAs($admin)
        ->post(route('trash.restore'), [
            'type' => 'Shift',
            'id' => $shift->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(Shift::find($shift->id))->not->toBeNull();
});

it('restores a soft-deleted saleReturn', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $ret = SaleReturn::factory()->create();
    $ret->delete();

    actingAs($admin)
        ->post(route('trash.restore'), [
            'type' => 'SaleReturn',
            'id' => $ret->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(SaleReturn::find($ret->id))->not->toBeNull();
});

it('restores a soft-deleted stockAdjustment', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $adj = StockAdjustment::factory()->create();
    $adj->delete();

    actingAs($admin)
        ->post(route('trash.restore'), [
            'type' => 'StockAdjustment',
            'id' => $adj->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(StockAdjustment::find($adj->id))->not->toBeNull();
});

// --- Force delete new model types ---

it('force deletes a soft-deleted warehouse', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $warehouse = Warehouse::factory()->create();
    $warehouse->delete();

    actingAs($admin)
        ->delete(route('trash.force-destroy'), [
            'type' => 'Warehouse',
            'id' => $warehouse->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(Warehouse::withTrashed()->where('id', $warehouse->id)->exists())->toBeFalse();
});

it('force deletes a soft-deleted paymentTransaction', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $tx = PaymentTransaction::factory()->create();
    $tx->delete();

    actingAs($admin)
        ->delete(route('trash.force-destroy'), [
            'type' => 'PaymentTransaction',
            'id' => $tx->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(PaymentTransaction::withTrashed()->where('id', $tx->id)->exists())->toBeFalse();
});

it('force deletes a soft-deleted priceHistory', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $ph = PriceHistory::factory()->create();
    $ph->delete();

    actingAs($admin)
        ->delete(route('trash.force-destroy'), [
            'type' => 'PriceHistory',
            'id' => $ph->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(PriceHistory::withTrashed()->where('id', $ph->id)->exists())->toBeFalse();
});

it('force deletes a soft-deleted saleEmail', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $email = SaleEmail::factory()->create();
    $email->delete();

    actingAs($admin)
        ->delete(route('trash.force-destroy'), [
            'type' => 'SaleEmail',
            'id' => $email->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(SaleEmail::withTrashed()->where('id', $email->id)->exists())->toBeFalse();
});

it('force deletes a soft-deleted stockTransfer', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $transfer = StockTransfer::factory()->create();
    $transfer->delete();

    actingAs($admin)
        ->delete(route('trash.force-destroy'), [
            'type' => 'StockTransfer',
            'id' => $transfer->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(StockTransfer::withTrashed()->where('id', $transfer->id)->exists())->toBeFalse();
});

// --- Show detail new model types ---

it('shows trashed warehouse detail page', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $warehouse = Warehouse::factory()->create(['name' => 'Gudang Detail']);
    $warehouse->delete();

    actingAs($admin)
        ->get(route('trash.show', ['type' => 'Warehouse', 'id' => $warehouse->id]))
        ->assertSuccessful();
});

it('shows trashed stockLog detail page', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $log = StockLog::factory()->create();
    $log->delete();

    actingAs($admin)
        ->get(route('trash.show', ['type' => 'StockLog', 'id' => $log->id]))
        ->assertSuccessful();
});

it('shows trashed purchaseItem detail page', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $item = PurchaseItem::factory()->create();
    $item->delete();

    actingAs($admin)
        ->get(route('trash.show', ['type' => 'PurchaseItem', 'id' => $item->id]))
        ->assertSuccessful();
});

it('shows trashed saleItem detail page', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $item = SaleItem::factory()->create();
    $item->delete();

    actingAs($admin)
        ->get(route('trash.show', ['type' => 'SaleItem', 'id' => $item->id]))
        ->assertSuccessful();
});

it('shows trashed saleReturnItem detail page', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $item = SaleReturnItem::factory()->create();
    $item->delete();

    actingAs($admin)
        ->get(route('trash.show', ['type' => 'SaleReturnItem', 'id' => $item->id]))
        ->assertSuccessful();
});

it('shows trashed paymentTransaction detail page', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $tx = PaymentTransaction::factory()->create();
    $tx->delete();

    actingAs($admin)
        ->get(route('trash.show', ['type' => 'PaymentTransaction', 'id' => $tx->id]))
        ->assertSuccessful();
});

it('restores a soft-deleted saleEmail', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $email = SaleEmail::factory()->create(['email' => 'restore@test.com']);
    $email->delete();

    actingAs($admin)
        ->post(route('trash.restore'), [
            'type' => 'SaleEmail',
            'id' => $email->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(SaleEmail::find($email->id))->not->toBeNull();
});
