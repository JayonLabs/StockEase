<?php

use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertModelExists;
use function Pest\Laravel\assertSoftDeleted;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    /** @var TestCase&object{admin: User, cashier: User, warehouse: User} $this */
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->warehouse = User::factory()->create(['role' => 'warehouse']);
    $this->cashier = User::factory()->create(['role' => 'cashier']);
});

// ============================================================
// Authorization
// ============================================================

describe('Authorization', function () {
    it('redirects unauthenticated users from all unit routes', function (string $route, string $method) {
        $unit = Unit::factory()->create();

        $routeParam = in_array($route, ['unit.update', 'unit.destroy']) ? $unit : null;

        $this->$method(
            $routeParam ? route($route, $routeParam) : route($route)
        )->assertRedirect(route('login'));
    })->with([
        'index' => ['unit.index',   'get'],
        'store' => ['unit.store',   'post'],
        'update' => ['unit.update',  'put'],
        'destroy' => ['unit.destroy', 'delete'],
    ]);

    it('forbids cashier from accessing unit routes', function (string $route, string $method) {
        $unit = Unit::factory()->create();

        $routeParam = in_array($route, ['unit.update', 'unit.destroy']) ? $unit : null;

        /** @var TestCase&object{admin: User, cashier: User, warehouse: User} $this */
        actingAs($this->cashier)->$method(
            $routeParam ? route($route, $routeParam) : route($route)
        )->assertForbidden();
    })->with([
        'index' => ['unit.index',   'get'],
        'store' => ['unit.store',   'post'],
        'update' => ['unit.update',  'put'],
        'destroy' => ['unit.destroy', 'delete'],
    ]);

    it('allows admin to access unit index', function () {
        /** @var TestCase&object{admin: User, cashier: User, warehouse: User} $this */
        actingAs($this->admin)->get(route('unit.index'))->assertSuccessful();
    });

    it('allows admin to access unit store', function () {
        /** @var TestCase&object{admin: User, cashier: User, warehouse: User} $this */
        actingAs($this->admin)->post(route('unit.store'), ['name' => 'Test Store Admin', 'short_name' => 'TSA'])
            ->assertRedirect();
    });

    it('allows admin to access unit update', function () {
        $unit = Unit::factory()->create();
        /** @var TestCase&object{admin: User, cashier: User, warehouse: User} $this */
        actingAs($this->admin)->put(route('unit.update', $unit), ['name' => 'Updated Admin', 'short_name' => 'UA'])
            ->assertRedirect();
    });

    it('allows admin to access unit destroy', function () {
        $unit = Unit::factory()->create();
        /** @var TestCase&object{admin: User, cashier: User, warehouse: User} $this */
        actingAs($this->admin)->delete(route('unit.destroy', $unit))
            ->assertRedirect();
    });

    it('allows warehouse to access unit index', function () {
        /** @var TestCase&object{admin: User, cashier: User, warehouse: User} $this */
        actingAs($this->warehouse)->get(route('unit.index'))->assertSuccessful();
    });

    it('allows warehouse to access unit store', function () {
        /** @var TestCase&object{admin: User, cashier: User, warehouse: User} $this */
        actingAs($this->warehouse)->post(route('unit.store'), ['name' => 'Test Store Whse', 'short_name' => 'TSW'])
            ->assertRedirect();
    });

    it('allows warehouse to access unit update', function () {
        $unit = Unit::factory()->create();
        /** @var TestCase&object{admin: User, cashier: User, warehouse: User} $this */
        actingAs($this->warehouse)->put(route('unit.update', $unit), ['name' => 'Updated Whse', 'short_name' => 'UW'])
            ->assertRedirect();
    });

    it('allows warehouse to access unit destroy', function () {
        $unit = Unit::factory()->create();
        /** @var TestCase&object{admin: User, cashier: User, warehouse: User} $this */
        actingAs($this->warehouse)->delete(route('unit.destroy', $unit))
            ->assertRedirect();
    });
});

// ============================================================
// Index — listing & pagination
// ============================================================

describe('Index', function () {
    it('renders the Unit/Index page', function () {
        /** @var TestCase&object{admin: User, cashier: User, warehouse: User} $this */
        actingAs($this->admin)
            ->get(route('unit.index'))
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->component('Unit/Index'));
    });

    it('paginates units with default 10 per page', function () {
        Unit::factory()->count(12)->create();

        /** @var TestCase&object{admin: User, cashier: User, warehouse: User} $this */
        actingAs($this->admin)
            ->get(route('unit.index'))
            ->assertInertia(
                fn ($page) => $page
                    ->has('units.data', 10)
                    ->where('units.total', 27) // 12 + 15 seeded
            );
    });

    it('shows exact count when below default per page', function () {
        Unit::factory()->count(3)->create();

        /** @var TestCase&object{admin: User, cashier: User, warehouse: User} $this */
        actingAs($this->admin)
            ->get(route('unit.index'))
            ->assertInertia(
                fn ($page) => $page
                    ->has('units.data', 10) // default per_page is 10, total is 3+15=18
                    ->where('units.total', 18)
            );
    });

    it('respects per_page query parameter', function () {
        Unit::factory()->count(10)->create();

        /** @var TestCase&object{admin: User, cashier: User, warehouse: User} $this */
        actingAs($this->admin)
            ->get(route('unit.index', ['per_page' => 5]))
            ->assertInertia(fn ($page) => $page->has('units.data', 5));
    });

    it('filters units by name search', function () {
        Unit::factory()->create(['name' => 'SpecialKilogram', 'short_name' => 'skg']);
        Unit::factory()->create(['name' => 'Gallon', 'short_name' => 'GAL']);
        Unit::factory()->create(['name' => 'SpecialGram', 'short_name' => 'sgr']);

        /** @var TestCase&object{admin: User, cashier: User, warehouse: User} $this */
        actingAs($this->admin)
            ->get(route('unit.index', ['search' => 'Special']))
            ->assertInertia(fn ($page) => $page->has('units.data', 2));
    });

    it('filters units by short_name search', function () {
        Unit::factory()->create(['name' => 'KilogramUnique', 'short_name' => 'kgu']);
        Unit::factory()->create(['name' => 'Gallon', 'short_name' => 'GAL']);

        /** @var TestCase&object{admin: User, cashier: User, warehouse: User} $this */
        actingAs($this->admin)
            ->get(route('unit.index', ['search' => 'kgu']))
            ->assertInertia(fn ($page) => $page->has('units.data', 1));
    });

    it('returns empty list when search has no match', function () {
        Unit::factory()->count(3)->create();

        /** @var TestCase&object{admin: User, cashier: User, warehouse: User} $this */
        actingAs($this->admin)
            ->get(route('unit.index', ['search' => 'xyznonexistent']))
            ->assertInertia(fn ($page) => $page->has('units.data', 0));
    });

    it('returns units ordered by name ascending', function () {
        Unit::factory()->create(['name' => 'AAA_Zulu']);
        Unit::factory()->create(['name' => 'AAA_Alpha']);
        Unit::factory()->create(['name' => 'AAA_Mike']);

        /** @var TestCase&object{admin: User, cashier: User, warehouse: User} $this */
        actingAs($this->admin)
            ->get(route('unit.index'))
            ->assertInertia(
                fn ($page) => $page
                    ->where('units.data.0.name', 'AAA_Alpha')
                    ->where('units.data.1.name', 'AAA_Mike')
                    ->where('units.data.2.name', 'AAA_Zulu')
            );
    });

    it('passes units prop with paginator structure', function () {
        /** @var TestCase&object{admin: User, cashier: User, warehouse: User} $this */
        actingAs($this->admin)
            ->get(route('unit.index'))
            ->assertInertia(
                fn ($page) => $page
                    ->has('units.data')
                    ->has('units.current_page')
                    ->has('units.per_page')
                    ->has('units.total')
            );
    });
});

// ============================================================
// Store
// ============================================================

describe('Store', function () {
    it('creates a unit and redirects back with success message', function () {
        /** @var TestCase&object{admin: User, cashier: User, warehouse: User} $this */
        actingAs($this->admin)
            ->post(route('unit.store'), [
                'name' => 'Kilogram Unique',
                'short_name' => 'kgu-store',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        assertDatabaseHas('units', [
            'name' => 'Kilogram Unique',
            'short_name' => 'kgu-store',
            'slug' => 'kilogram-unique',
        ]);
    });

    it('generates slug from name on creation', function () {
        /** @var TestCase&object{admin: User, cashier: User, warehouse: User} $this */
        actingAs($this->admin)
            ->post(route('unit.store'), [
                'name' => 'Meter Persegi',
                'short_name' => 'm2',
            ]);

        assertDatabaseHas('units', ['slug' => 'meter-persegi']);
    });

    it('generates unique slug when name would conflict', function () {
        Unit::factory()->create(['name' => 'Collision Test']);

        /** @var TestCase&object{admin: User, cashier: User, warehouse: User} $this */
        actingAs($this->admin)
            ->post(route('unit.store'), [
                'name' => 'Collision Test!!!', // Different name, same slug base
                'short_name' => 'CT-NEW',
            ]);

        assertDatabaseHas('units', [
            'name' => 'Collision Test!!!',
            'slug' => 'collision-test-2',
        ]);
    });

    it('validates required fields', function (array $data, array $errors) {
        /** @var TestCase&object{admin: User, cashier: User, warehouse: User} $this */
        actingAs($this->admin)
            ->post(route('unit.store'), $data)
            ->assertSessionHasErrors($errors);
    })->with([
        'empty name' => [['name' => '',                    'short_name' => 'A'],  ['name']],
        'empty short_name' => [['name' => 'Valid',               'short_name' => ''],   ['short_name']],
        'missing name' => [['short_name' => 'A'],                                   ['name']],
        'missing short_name' => [['name' => 'Valid'],                                     ['short_name']],
        'name too long' => [['name' => str_repeat('a', 256), 'short_name' => 'A'],   ['name']],
    ]);

    it('does not create unit when validation fails', function () {
        $countBefore = Unit::count();

        /** @var TestCase&object{admin: User, cashier: User, warehouse: User} $this */
        actingAs($this->admin)
            ->post(route('unit.store'), ['name' => '', 'short_name' => '']);

        expect(Unit::count())->toBe($countBefore);
    });
});

// ============================================================
// Update
// ============================================================

describe('Update', function () {
    it('updates a unit and redirects back with success message', function () {
        $unit = Unit::factory()->create(['name' => 'Old Name Unique', 'short_name' => 'ONU', 'slug' => 'old-name-unique']);

        /** @var TestCase&object{admin: User, cashier: User, warehouse: User} $this */
        actingAs($this->admin)
            ->put(route('unit.update', $unit), [
                'name' => 'New Name Unique',
                'short_name' => 'NNU',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        assertDatabaseHas('units', [
            'id' => $unit->id,
            'name' => 'New Name Unique',
            'short_name' => 'NNU',
            'slug' => 'new-name-unique',
        ]);
    });

    it('regenerates slug when name changes', function () {
        $unit = Unit::factory()->create(['name' => 'Old Name Again', 'slug' => 'old-name-again']);

        /** @var TestCase&object{admin: User, cashier: User, warehouse: User} $this */
        actingAs($this->admin)
            ->put(route('unit.update', $unit), [
                'name' => 'Brand New Name',
                'short_name' => 'BNN',
            ]);

        assertDatabaseHas('units', ['id' => $unit->id, 'slug' => 'brand-new-name']);
    });

    it('preserves slug when name is unchanged', function () {
        $unit = Unit::factory()->create([
            'name' => 'Preserved Name',
            'short_name' => 'pn',
            'slug' => 'preserved-name',
        ]);

        /** @var TestCase&object{admin: User, cashier: User, warehouse: User} $this */
        actingAs($this->admin)
            ->put(route('unit.update', $unit), [
                'name' => 'Preserved Name',
                'short_name' => 'PN-NEW', // only short_name changes
            ]);

        assertDatabaseHas('units', [
            'id' => $unit->id,
            'short_name' => 'PN-NEW',
            'slug' => 'preserved-name', // unchanged
        ]);
    });

    it('validates required fields on update', function (array $data, array $errors) {
        $unit = Unit::factory()->create();

        /** @var TestCase&object{admin: User, cashier: User, warehouse: User} $this */
        actingAs($this->admin)
            ->put(route('unit.update', $unit), $data)
            ->assertSessionHasErrors($errors);
    })->with([
        'empty name' => [['name' => '',     'short_name' => 'A'], ['name']],
        'empty short_name' => [['name' => 'Valid', 'short_name' => ''], ['short_name']],
    ]);

    it('returns 404 for non-existent unit on update', function () {
        /** @var TestCase&object{admin: User, cashier: User, warehouse: User} $this */
        actingAs($this->admin)
            ->put(route('unit.update', 'non-existent-slug'), [
                'name' => 'Test',
                'short_name' => 'T',
            ])
            ->assertNotFound();
    });
});

// ============================================================
// Destroy
// ============================================================

describe('Destroy', function () {
    it('deletes a unit and redirects back with success message', function () {
        $unit = Unit::factory()->create();

        /** @var TestCase&object{admin: User, cashier: User, warehouse: User} $this */
        actingAs($this->admin)
            ->delete(route('unit.destroy', $unit))
            ->assertRedirect()
            ->assertSessionHas('success');

        assertSoftDeleted('units', ['id' => $unit->id]);
    });

    it('returns 404 for non-existent unit on delete', function () {
        /** @var TestCase&object{admin: User, cashier: User, warehouse: User} $this */
        actingAs($this->admin)
            ->delete(route('unit.destroy', 'non-existent-slug'))
            ->assertNotFound();
    });

    it('does not affect other units when one is deleted', function () {
        $unitToDelete = Unit::factory()->create();
        $unitToKeep = Unit::factory()->create();

        /** @var TestCase&object{admin: User, cashier: User, warehouse: User} $this */
        actingAs($this->admin)->delete(route('unit.destroy', $unitToDelete));

        assertModelExists($unitToKeep);
    });
});
