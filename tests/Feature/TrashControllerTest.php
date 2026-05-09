<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;

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
