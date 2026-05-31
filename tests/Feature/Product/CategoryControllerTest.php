<?php

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertSoftDeleted;
use function Pest\Laravel\get;

uses(LazilyRefreshDatabase::class);

// -- AUTHORIZATION --

it('denies unauthenticated users to access categories', function () {
    get(route('category.index'))->assertRedirect(route('login'));
});

it('denies non-admin users to access categories', function (string $role) {
    /** @var User $user */
    $user = User::factory()->create(['role' => $role]);

    actingAs($user)->get(route('category.index'))->assertForbidden();
})->with(['cashier', 'warehouse']);

// -- INDEX --

it('allows admin to view categories', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    Category::factory()->count(3)->create();

    actingAs($admin)
        ->get(route('category.index'))
        ->assertSuccessful()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Category/Index')
                ->has('categories.data', 3)
        );
});

it('can filter categories by search term', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    Category::factory()->create(['name' => 'Apple']);
    Category::factory()->create(['name' => 'Banana']);

    actingAs($admin)
        ->get(route('category.index', ['search' => 'Apple']))
        ->assertSuccessful()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Category/Index')
                ->has('categories.data', 1)
                ->where('categories.data.0.name', 'Apple')
        );
});

it('can paginate categories', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    Category::factory()->count(15)->create();

    actingAs($admin)
        ->get(route('category.index', ['per_page' => 5]))
        ->assertSuccessful()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Category/Index')
                ->has('categories.data', 5)
                ->where('categories.per_page', 5)
        );
});

// -- DISABLED ROUTES --

it('aborts on create, show, and edit routes', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::factory()->create();

    actingAs($admin);

    // Laravel returns 405 (Method Not Allowed) because these paths match
    // the category/{category} pattern which is defined for PUT/DELETE
    get('/category/create')->assertStatus(405);
    get("/category/{$category->slug}")->assertStatus(405);
    // This path doesn't match any defined pattern, so it's a 404
    get("/category/{$category->slug}/edit")->assertNotFound();
});

// -- STORE --

it('allows admin to create a category', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)
        ->post(route('category.store'), [
            'name' => 'New Category',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Kategory berhasil ditambahkan');

    assertDatabaseHas('categories', [
        'name' => 'New Category',
        'slug' => 'new-category',
    ]);
});

it('validates category creation', function ($data, $errors) {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)
        ->post(route('category.store'), $data)
        ->assertSessionHasErrors($errors);
})->with([
    'empty name' => [['name' => ''], ['name']],
    'name too long' => [['name' => str_repeat('a', 256)], ['name']],
]);

// -- UPDATE --

it('allows admin to update a category name and slug', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::factory()->create(['name' => 'Old Name', 'slug' => 'old-name']);

    actingAs($admin)
        ->put(route('category.update', $category), [
            'name' => 'Updated Name',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Kategory berhasil diupdate');

    assertDatabaseHas('categories', [
        'id' => $category->id,
        'name' => 'Updated Name',
        'slug' => 'updated-name',
    ]);
});

it('does not change slug if category name is not changed on update', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::factory()->create(['name' => 'Same Name', 'slug' => 'same-name-123']);

    actingAs($admin)
        ->put(route('category.update', $category), [
            'name' => 'Same Name',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Kategory berhasil diupdate');

    assertDatabaseHas('categories', [
        'id' => $category->id,
        'name' => 'Same Name',
        'slug' => 'same-name-123',
    ]);
});

it('validates category update', function ($data, $errors) {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::factory()->create();

    actingAs($admin)
        ->put(route('category.update', $category), $data)
        ->assertSessionHasErrors($errors);
})->with([
    'empty name' => [['name' => ''], ['name']],
    'name too long' => [['name' => str_repeat('a', 256)], ['name']],
]);

// -- DESTROY --

it('allows admin to delete a category', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::factory()->create();

    actingAs($admin)
        ->delete(route('category.destroy', $category))
        ->assertRedirect()
        ->assertSessionHas('success', 'Kategory berhasil dihapus');

    assertSoftDeleted('categories', ['id' => $category->id]);
});
