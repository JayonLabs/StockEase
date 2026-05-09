<?php

use App\Actions\Sale\RecalculateSaleTotal;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertModelExists;
use function Pest\Laravel\assertSoftDeleted;
use function Pest\Laravel\get;

// ─── Discount Calculation Tests ───────────────────────────────────────────────

it('can calculate total with percentage promotion', function () {
    $product = Product::factory()->create(['selling_price' => 100000]);
    $sale = Sale::factory()->create();

    Promotion::factory()->create([
        'type' => 'percentage',
        'discount_value' => 10,
        'product_id' => $product->id,
        'start_date' => now()->subDay(),
        'end_date' => now()->addDay(),
        'is_active' => true,
    ]);

    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'qty' => 2,
        'price' => 100000,
    ]);

    $total = (new RecalculateSaleTotal)->execute($sale);

    // 2 × 100,000 = 200,000 → 10% off = 180,000
    expect($total)->toEqual(180000);
});

it('can calculate total with nominal promotion', function () {
    $product = Product::factory()->create(['selling_price' => 50000]);
    $sale = Sale::factory()->create();

    Promotion::factory()->create([
        'type' => 'nominal',
        'discount_value' => 5000,
        'product_id' => $product->id,
        'start_date' => now()->subDay(),
        'end_date' => now()->addDay(),
        'is_active' => true,
    ]);

    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'qty' => 3,
        'price' => 50000,
    ]);

    $total = (new RecalculateSaleTotal)->execute($sale);

    // 3 × 50,000 = 150,000 → 3 × 5,000 off = 135,000
    expect($total)->toEqual(135000);
});

it('can calculate total with bogo promotion', function () {
    $product = Product::factory()->create(['selling_price' => 50000]);
    $sale = Sale::factory()->create();

    Promotion::factory()->create([
        'type' => 'bogo',
        'buy_qty' => 2,
        'get_qty' => 1,
        'product_id' => $product->id,
        'start_date' => now()->subDay(),
        'end_date' => now()->addDay(),
        'is_active' => true,
    ]);

    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'qty' => 3,
        'price' => 50000,
    ]);

    $total = (new RecalculateSaleTotal)->execute($sale);

    // 3 × 50,000 = 150,000 → 1 free item (floor(3/3)*1) = 50,000 → 100,000
    expect($total)->toEqual(100000);
});

it('does not apply inactive or expired promotions', function () {
    $product = Product::factory()->create(['selling_price' => 100000]);
    $sale = Sale::factory()->create();

    // Expired promotion
    Promotion::factory()->create([
        'type' => 'percentage',
        'discount_value' => 50,
        'product_id' => $product->id,
        'start_date' => now()->subDays(5),
        'end_date' => now()->subDays(1),
        'is_active' => true,
    ]);

    // Inactive promotion
    Promotion::factory()->create([
        'type' => 'percentage',
        'discount_value' => 50,
        'product_id' => $product->id,
        'start_date' => now()->subDay(),
        'end_date' => now()->addDay(),
        'is_active' => false,
    ]);

    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'qty' => 1,
        'price' => 100000,
    ]);

    $total = (new RecalculateSaleTotal)->execute($sale);

    expect($total)->toEqual(100000);
});

// ─── Authorization Tests ───────────────────────────────────────────────────────

it('guest cannot access promotions index', function () {
    get(route('promotions.index'))
        ->assertRedirect(route('login'));
});

it('non-admin cashier cannot access promotions index', function () {

    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);

    actingAs($cashier)->get(route('promotions.index'))
        ->assertForbidden();
});

it('admin can access promotions index', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)->get(route('promotions.index'))
        ->assertSuccessful()
        ->assertInertia(
            fn ($page) => $page
                ->component('Promotions/Index')
                ->has('promotions')
                ->has('categories')
                ->has('products'),
        );
});

// ─── Store Tests ───────────────────────────────────────────────────────────────

it('admin can create a percentage promotion', function () {

    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $product = Product::factory()->create();

    actingAs($admin)->post(route('promotions.store'), [
        'name' => 'Diskon 10%',
        'type' => 'percentage',
        'discount_value' => 10,
        'product_id' => $product->id,
        'start_date' => now()->format('Y-m-d\TH:i'),
        'end_date' => now()->addDay()->format('Y-m-d\TH:i'),
        'is_active' => true,
    ])
        ->assertRedirect(route('promotions.index'));

    assertDatabaseHas('promotions', [
        'name' => 'Diskon 10%',
        'type' => 'percentage',
        'discount_value' => 10,
        'product_id' => $product->id,
    ]);
});

it('admin can create a nominal promotion', function () {

    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)->post(route('promotions.store'), [
        'name' => 'Diskon Rp5000',
        'type' => 'nominal',
        'discount_value' => 5000,
        'start_date' => now()->format('Y-m-d\TH:i'),
        'end_date' => now()->addDay()->format('Y-m-d\TH:i'),
        'is_active' => true,
    ])
        ->assertRedirect(route('promotions.index'));

    assertDatabaseHas('promotions', [
        'name' => 'Diskon Rp5000',
        'type' => 'nominal',
    ]);
});

it('admin can create a bogo promotion', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $product = Product::factory()->create();

    actingAs($admin)->post(route('promotions.store'), [
        'name' => 'Beli 2 Gratis 1',
        'type' => 'bogo',
        'buy_qty' => 2,
        'get_qty' => 1,
        'product_id' => $product->id,
        'start_date' => now()->format('Y-m-d\TH:i'),
        'end_date' => now()->addDay()->format('Y-m-d\TH:i'),
        'is_active' => true,
    ])
        ->assertRedirect(route('promotions.index'));

    assertDatabaseHas('promotions', [
        'name' => 'Beli 2 Gratis 1',
        'type' => 'bogo',
        'buy_qty' => 2,
        'get_qty' => 1,
    ]);
});

it('admin can create a category-scoped promotion', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::factory()->create();

    actingAs($admin)->post(route('promotions.store'), [
        'name' => 'Diskon Kategori',
        'type' => 'percentage',
        'discount_value' => 15,
        'category_id' => $category->id,
        'start_date' => now()->format('Y-m-d\TH:i'),
        'end_date' => now()->addDay()->format('Y-m-d\TH:i'),
        'is_active' => true,
    ])
        ->assertRedirect(route('promotions.index'));

    assertDatabaseHas('promotions', [
        'name' => 'Diskon Kategori',
        'category_id' => $category->id,
    ]);
});

// ─── Store Validation Tests ────────────────────────────────────────────────────

it('store fails when name is missing', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)->post(route('promotions.store'), [
        'type' => 'percentage',
        'discount_value' => 10,
        'start_date' => now()->format('Y-m-d\TH:i'),
        'end_date' => now()->addDay()->format('Y-m-d\TH:i'),
    ])
        ->assertSessionHasErrors('name');
});

it('store fails when type is invalid', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)->post(route('promotions.store'), [
        'name' => 'Test',
        'type' => 'invalid_type',
        'discount_value' => 10,
        'start_date' => now()->format('Y-m-d\TH:i'),
        'end_date' => now()->addDay()->format('Y-m-d\TH:i'),
    ])
        ->assertSessionHasErrors('type');
});

it('store fails when discount_value is missing for percentage type', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)->post(route('promotions.store'), [
        'name' => 'Diskon 10%',
        'type' => 'percentage',
        'start_date' => now()->format('Y-m-d\TH:i'),
        'end_date' => now()->addDay()->format('Y-m-d\TH:i'),
    ])
        ->assertSessionHasErrors('discount_value');
});

it('store fails when discount_value is missing for nominal type', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)->post(route('promotions.store'), [
        'name' => 'Diskon Nominal',
        'type' => 'nominal',
        'start_date' => now()->format('Y-m-d\TH:i'),
        'end_date' => now()->addDay()->format('Y-m-d\TH:i'),
    ])
        ->assertSessionHasErrors('discount_value');
});

it('store fails when percentage discount_value exceeds 100', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)->post(route('promotions.store'), [
        'name' => 'Diskon 110%',
        'type' => 'percentage',
        'discount_value' => 110,
        'start_date' => now()->format('Y-m-d\TH:i'),
        'end_date' => now()->addDay()->format('Y-m-d\TH:i'),
    ])
        ->assertSessionHasErrors('discount_value');
});

it('store fails when bogo buy_qty is missing', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)->post(route('promotions.store'), [
        'name' => 'BOGO',
        'type' => 'bogo',
        'get_qty' => 1,
        'start_date' => now()->format('Y-m-d\TH:i'),
        'end_date' => now()->addDay()->format('Y-m-d\TH:i'),
    ])
        ->assertSessionHasErrors('buy_qty');
});

it('store fails when bogo get_qty is missing', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)->post(route('promotions.store'), [
        'name' => 'BOGO',
        'type' => 'bogo',
        'buy_qty' => 2,
        'start_date' => now()->format('Y-m-d\TH:i'),
        'end_date' => now()->addDay()->format('Y-m-d\TH:i'),
    ])
        ->assertSessionHasErrors('get_qty');
});

it('store fails when end_date is before start_date', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)->post(route('promotions.store'), [
        'name' => 'Flash Sale',
        'type' => 'percentage',
        'discount_value' => 10,
        'start_date' => now()->addDay()->format('Y-m-d\TH:i'),
        'end_date' => now()->format('Y-m-d\TH:i'),
    ])
        ->assertSessionHasErrors('end_date');
});

it('store fails when product_id does not exist', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)->post(route('promotions.store'), [
        'name' => 'Test',
        'type' => 'percentage',
        'discount_value' => 10,
        'product_id' => 99999,
        'start_date' => now()->format('Y-m-d\TH:i'),
        'end_date' => now()->addDay()->format('Y-m-d\TH:i'),
    ])
        ->assertSessionHasErrors('product_id');
});

// ─── Update Tests ──────────────────────────────────────────────────────────────

it('admin can update a promotion', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $promotion = Promotion::factory()->create([
        'type' => 'percentage',
        'discount_value' => 10,
    ]);

    actingAs($admin)->put(route('promotions.update', $promotion->id), [
        'name' => 'Updated Promo',
        'type' => 'nominal',
        'discount_value' => 20000,
        'start_date' => now()->format('Y-m-d\TH:i'),
        'end_date' => now()->addDays(7)->format('Y-m-d\TH:i'),
        'is_active' => false,
    ])
        ->assertRedirect(route('promotions.index'));

    expect($promotion->fresh())
        ->name->toBe('Updated Promo')
        ->type->toBe('nominal')
        ->is_active->toBeFalse();
});

it('update fails with invalid data', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $promotion = Promotion::factory()->create();

    actingAs($admin)->put(route('promotions.update', $promotion->id), [
        'name' => '',
        'type' => 'invalid',
        'start_date' => now()->format('Y-m-d\TH:i'),
        'end_date' => now()->addDay()->format('Y-m-d\TH:i'),
    ])
        ->assertSessionHasErrors(['name', 'type']);
});

it('non-admin cannot update a promotion', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);
    $promotion = Promotion::factory()->create();

    actingAs($cashier)->put(route('promotions.update', $promotion->id), [
        'name' => 'Hacked',
        'type' => 'percentage',
        'discount_value' => 50,
        'start_date' => now()->format('Y-m-d\TH:i'),
        'end_date' => now()->addDay()->format('Y-m-d\TH:i'),
    ])
        ->assertForbidden();
});

// ─── Delete Tests ──────────────────────────────────────────────────────────────

it('admin can delete a promotion', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $promotion = Promotion::factory()->create();

    actingAs($admin)->delete(route('promotions.destroy', $promotion->id))
        ->assertRedirect(route('promotions.index'));

    assertSoftDeleted('promotions', ['id' => $promotion->id]);
});

it('non-admin cannot delete a promotion', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);
    $promotion = Promotion::factory()->create();

    actingAs($cashier)->delete(route('promotions.destroy', $promotion->id))
        ->assertForbidden();

    assertModelExists($promotion);
});

// ─── Index Data Tests ──────────────────────────────────────────────────────────

it('index returns paginated promotions with category and product', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $product = Product::factory()->create();
    $category = Category::factory()->create();

    Promotion::factory()->create([
        'product_id' => $product->id,
        'category_id' => null,
    ]);

    Promotion::factory()->create([
        'category_id' => $category->id,
        'product_id' => null,
    ]);

    actingAs($admin)->get(route('promotions.index'))
        ->assertSuccessful()
        ->assertInertia(
            fn ($page) => $page
                ->component('Promotions/Index')
                ->has('promotions.data', 2)
                ->has('categories')
                ->has('products'),
        );
});

// ─── Search Filter Tests ──────────────────────────────────────────────────────────

it('can filter promotions by name', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);

    Promotion::factory()->create(['name' => 'Diskon Spesial']);
    Promotion::factory()->create(['name' => 'Diskon Biasa']);
    Promotion::factory()->create(['name' => 'Flash Sale']);

    actingAs($admin)->get(route('promotions.index', ['search' => 'Spesial']))
        ->assertSuccessful()
        ->assertInertia(
            fn ($page) => $page
                ->has('promotions.data', 1)
                ->where('promotions.data.0.name', 'Diskon Spesial'),
        );
});

it('can filter promotions by product name', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);

    $product = Product::factory()->create(['name' => 'Susu Beruang']);

    Promotion::factory()->create([
        'product_id' => $product->id,
    ]);

    Promotion::factory()->create();

    actingAs($admin)->get(route('promotions.index', ['search' => 'Beruang']))
        ->assertSuccessful()
        ->assertInertia(
            fn ($page) => $page
                ->has('promotions.data', 1),
        );
});

it('can filter promotions by category name', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);

    $category = Category::factory()->create(['name' => 'Minuman']);

    Promotion::factory()->create([
        'category_id' => $category->id,
    ]);

    Promotion::factory()->create();

    actingAs($admin)->get(route('promotions.index', ['search' => 'Minum']))
        ->assertSuccessful()
        ->assertInertia(
            fn ($page) => $page
                ->has('promotions.data', 1),
        );
});

it('returns empty when search has no match', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);

    Promotion::factory()->create(['name' => 'Diskon Test']);
    Promotion::factory()->create(['name' => 'Promo Lain']);

    actingAs($admin)->get(route('promotions.index', ['search' => 'TidakAda']))
        ->assertSuccessful()
        ->assertInertia(
            fn ($page) => $page
                ->has('promotions.data', 0),
        );
});

it('returns empty data when search is blank', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);

    Promotion::factory()->count(3)->create();

    actingAs($admin)->get(route('promotions.index', ['search' => '']))
        ->assertSuccessful()
        ->assertInertia(
            fn ($page) => $page
                ->has('promotions.data', 3),
        );
});

it('search is case insensitive', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);

    Promotion::factory()->create(['name' => 'DISKON MINGGUAN']);

    actingAs($admin)->get(route('promotions.index', ['search' => 'diskon']))
        ->assertSuccessful()
        ->assertInertia(
            fn ($page) => $page
                ->has('promotions.data', 1),
        );
});

// ─── Date Filter Tests ──────────────────────────────────────────────────────────

it('can filter promotions by date range', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);

    $promoInRange = Promotion::factory()->create([
        'start_date' => now()->subDay(),
        'end_date' => now()->addDays(5),
    ]);

    $promoOutOfRange = Promotion::factory()->create([
        'start_date' => now()->subDays(30),
        'end_date' => now()->subDays(10),
    ]);

    actingAs($admin)->get(route('promotions.index', [
        'start' => now()->subDay()->format('M d, Y'),
        'end' => now()->addDays(5)->format('M d, Y'),
    ]))
        ->assertSuccessful()
        ->assertInertia(
            fn ($page) => $page
                ->has('promotions.data', 1)
                ->where('promotions.data.0.id', $promoInRange->id),
        );
});

it('can filter promotions by start date only', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);

    $promo = Promotion::factory()->create([
        'start_date' => now(),
        'end_date' => now()->addDays(5),
    ]);

    Promotion::factory()->create([
        'start_date' => now()->addDays(10),
        'end_date' => now()->addDays(15),
    ]);

    actingAs($admin)->get(route('promotions.index', [
        'start' => now()->format('M d, Y'),
        'end' => now()->addDays(7)->format('M d, Y'),
    ]))
        ->assertSuccessful()
        ->assertInertia(
            fn ($page) => $page
                ->has('promotions.data', 1),
        );
});

it('can filter promotions by end date only', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);

    $promo = Promotion::factory()->create([
        'start_date' => now()->subDays(10),
        'end_date' => now()->subDay(),
    ]);

    Promotion::factory()->create([
        'start_date' => now()->subDays(5),
        'end_date' => now()->addDays(5),
    ]);

    actingAs($admin)->get(route('promotions.index', [
        'start' => now()->subDays(15)->format('M d, Y'),
        'end' => now()->format('M d, Y'),
    ]))
        ->assertSuccessful()
        ->assertInertia(
            fn ($page) => $page
                ->has('promotions.data', 1)
                ->where('promotions.data.0.id', $promo->id),
        );
});

it('returns empty when date filter has no match', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);

    Promotion::factory()->create([
        'start_date' => now()->addDays(10),
        'end_date' => now()->addDays(20),
    ]);

    actingAs($admin)->get(route('promotions.index', [
        'start' => now()->subDays(10)->format('M d, Y'),
        'end' => now()->subDay()->format('M d, Y'),
    ]))
        ->assertSuccessful()
        ->assertInertia(
            fn ($page) => $page
                ->has('promotions.data', 0),
        );
});

it('combines search and date filters', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);

    $promo = Promotion::factory()->create([
        'name' => 'Diskon Maret',
        'start_date' => now()->subDay(),
        'end_date' => now()->addDays(5),
    ]);

    Promotion::factory()->create([
        'name' => 'Diskon April',
        'start_date' => now()->subDays(10),
        'end_date' => now()->subDays(5),
    ]);

    Promotion::factory()->create([
        'name' => 'Flash Sale',
        'start_date' => now()->subDay(),
        'end_date' => now()->addDays(5),
    ]);

    actingAs($admin)->get(route('promotions.index', [
        'search' => 'Maret',
        'start' => now()->subDay()->format('M d, Y'),
        'end' => now()->addDays(5)->format('M d, Y'),
    ]))
        ->assertSuccessful()
        ->assertInertia(
            fn ($page) => $page
                ->has('promotions.data', 1)
                ->where('promotions.data.0.name', 'Diskon Maret'),
        );
});
