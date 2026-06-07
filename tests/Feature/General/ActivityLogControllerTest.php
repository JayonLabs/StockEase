<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Database\Factories\CompanyFactory;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    /** @var TestCase&object{viewPermission:Permission} $this */
    $this->seed(RoleAndPermissionSeeder::class);

    $this->viewPermission = Permission::findByName('view_activity_logs', 'web');

    $this->superAdmin = User::factory()->create();
    $this->superAdmin->syncRoles('super_admin');

    $this->admin = User::factory()->create();
    $this->admin->syncRoles('admin');

    $this->cashier = User::factory()->create();
    $this->cashier->syncRoles('cashier');
});

describe('Access Control', function () {
    it('redirects unauthenticated users to login', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        get(route('activity-logs.index'))
            ->assertRedirect(route('login'));
    });

    it('forbids admin user without view_activity_logs permission', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        actingAs($this->admin)
            ->get(route('activity-logs.index'))
            ->assertForbidden();
    });

    it('forbids cashier user without view_activity_logs permission', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        actingAs($this->cashier)
            ->get(route('activity-logs.index'))
            ->assertForbidden();
    });

    it('forbids super_admin without explicit view_activity_logs permission', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        actingAs($this->superAdmin)
            ->get(route('activity-logs.index'))
            ->assertForbidden();
    });

    it('allows user with view_activity_logs permission', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        $this->admin->givePermissionTo($this->viewPermission);

        actingAs($this->admin)
            ->get(route('activity-logs.index'))
            ->assertSuccessful();
    });

    it('allows super_admin with explicit view_activity_logs permission', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        $this->superAdmin->givePermissionTo($this->viewPermission);

        actingAs($this->superAdmin)
            ->get(route('activity-logs.index'))
            ->assertSuccessful();
    });

    it('forbids access to show page without view_activity_logs permission', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        $activity = activity()->causedBy($this->superAdmin)->log('no permission');

        actingAs($this->superAdmin)
            ->get(route('activity-logs.show', $activity))
            ->assertForbidden();
    });
});

describe('Page Rendering', function () {
    beforeEach(function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        $this->superAdmin->givePermissionTo($this->viewPermission);
        Activity::query()->delete();
    });

    it('renders the correct Inertia component', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        actingAs($this->superAdmin)
            ->get(route('activity-logs.index'))
            ->assertInertia(fn ($page) => $page->component('ActivityLog/Index'));
    });

    it('provides activities data structure', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        actingAs($this->superAdmin)
            ->get(route('activity-logs.index'))
            ->assertInertia(
                fn ($page) => $page
                    ->has('activities')
                    ->has('activities.data')
            );
    });

    it('provides events and logNames for filters', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        actingAs($this->superAdmin)
            ->get(route('activity-logs.index'))
            ->assertInertia(
                fn ($page) => $page
                    ->has('events')
                    ->has('logNames')
                    ->has('filters')
            );
    });

    it('shows empty state when no activities exist', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        actingAs($this->superAdmin)
            ->get(route('activity-logs.index'))
            ->assertInertia(
                fn ($page) => $page
                    ->where('activities.total', 0)
                    ->where('activities.data', [])
            );
    });
});

describe('Activity Logging', function () {
    beforeEach(function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        $this->superAdmin->givePermissionTo($this->viewPermission);
        Activity::query()->delete();
    });

    it('logs model creation', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        actingAs($this->superAdmin);

        $product = Product::factory()->create(['name' => 'Test Product']);

        // Product factory also creates Category + Unit, so the Product
        // activity is the last of the three
        $activities = Activity::where('subject_type', Product::class)->get();

        expect($activities)->toHaveCount(1);
        expect($activities->first()->event)->toBe('created');
        expect($activities->first()->causer_id)->toBe($this->superAdmin->id);
        expect($activities->first()->subject_id)->toBe($product->id);
    });

    it('logs model update', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        actingAs($this->superAdmin);

        $unit = Unit::factory()->create(['name' => 'Old Unit']);
        Activity::query()->delete();

        $unit->update(['name' => 'New Unit']);

        $activity = Activity::where('event', 'updated')->first();

        expect($activity)->not->toBeNull();
        expect($activity->subject_id)->toBe($unit->id);
        expect($activity->causer_id)->toBe($this->superAdmin->id);
    });

    it('logs model deletion', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        actingAs($this->superAdmin);

        $category = Category::factory()->create(['name' => 'To Delete']);
        Activity::query()->delete();

        $category->delete();

        $activity = Activity::where('event', 'deleted')->first();

        expect($activity)->not->toBeNull();
        expect($activity->subject_type)->toBe(Category::class);
        expect($activity->subject_id)->toBe($category->id);
    });

    it('has null causer when no authenticated user', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        $category = Category::factory()->create();

        $activity = Activity::latest()->first();

        expect($activity->causer_id)->toBeNull();
    });

    it('uses default log_name when not specified', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        actingAs($this->superAdmin);

        Category::factory()->create();

        $activity = Activity::latest()->first();

        expect($activity->log_name)->toBe('default');
    });
});

describe('Filtering', function () {
    beforeEach(function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        $this->superAdmin->givePermissionTo($this->viewPermission);
        Activity::query()->delete();
    });

    it('filters activities by search query on description', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        actingAs($this->superAdmin);

        Product::factory()->create(['name' => 'AlphaSearch']);

        // Search for 'created' which appears in description for new models
        actingAs($this->superAdmin)
            ->get(route('activity-logs.index', ['search' => 'created']))
            ->assertInertia(
                fn ($page) => $page
                    ->has('activities.data')
            );
    });

    it('filters activities by event', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        actingAs($this->superAdmin);

        // Create standalone models so we get exactly 1 activity each
        $cat = Category::factory()->create(['name' => 'Created']);
        Activity::query()->delete();

        // Create fresh + update + delete
        $cat2 = Category::factory()->create(['name' => 'Updated']);
        $cat2->update(['name' => 'Updated Cat']);
        $cat2->delete();

        actingAs($this->superAdmin)
            ->get(route('activity-logs.index', ['event' => 'created']))
            ->assertInertia(
                fn ($page) => $page
                    ->has('activities.data', 1)
            );

        actingAs($this->superAdmin)
            ->get(route('activity-logs.index', ['event' => 'updated']))
            ->assertInertia(
                fn ($page) => $page
                    ->has('activities.data', 1)
            );

        actingAs($this->superAdmin)
            ->get(route('activity-logs.index', ['event' => 'deleted']))
            ->assertInertia(
                fn ($page) => $page
                    ->has('activities.data', 1)
            );
    });

    it('filters activities by log_name', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        actingAs($this->superAdmin);

        Category::factory()->create();

        actingAs($this->superAdmin)
            ->get(route('activity-logs.index', ['log_name' => 'default']))
            ->assertInertia(
                fn ($page) => $page
                    ->has('activities.data', 1)
            );
    });

    it('returns empty results when log_name filter finds nothing', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        actingAs($this->superAdmin);

        Category::factory()->create();

        actingAs($this->superAdmin)
            ->get(route('activity-logs.index', ['log_name' => 'nonexistent']))
            ->assertInertia(
                fn ($page) => $page
                    ->where('activities.total', 0)
            );
    });

    it('returns empty results when search finds nothing', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        actingAs($this->superAdmin);

        Category::factory()->create(['name' => 'SomeCat']);

        actingAs($this->superAdmin)
            ->get(route('activity-logs.index', ['search' => 'NonExistentXYZ']))
            ->assertInertia(
                fn ($page) => $page
                    ->where('activities.total', 0)
            );
    });

    it('passes filter values back to the view', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        actingAs($this->superAdmin);

        actingAs($this->superAdmin)
            ->get(route('activity-logs.index', ['search' => 'test', 'event' => 'created']))
            ->assertInertia(
                fn ($page) => $page
                    ->where('filters.search', 'test')
                    ->where('filters.event', 'created')
            );
    });
});

describe('Pagination', function () {
    beforeEach(function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        $this->superAdmin->givePermissionTo($this->viewPermission);
        Activity::query()->delete();
    });

    it('paginates activities with 50 per page', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        actingAs($this->superAdmin);

        // Each Category factory creates exactly 1 activity
        Category::factory()->count(55)->create();

        actingAs($this->superAdmin)
            ->get(route('activity-logs.index'))
            ->assertInertia(
                fn ($page) => $page
                    ->has('activities.data', 50)
                    ->where('activities.per_page', 50)
                    ->where('activities.total', 55)
                    ->where('activities.last_page', 2)
            );
    });

    it('returns correct page 2 data', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        actingAs($this->superAdmin);

        Category::factory()->count(55)->create();

        actingAs($this->superAdmin)
            ->get(route('activity-logs.index', ['page' => 2]))
            ->assertInertia(
                fn ($page) => $page
                    ->has('activities.data', 5)
                    ->where('activities.current_page', 2)
            );
    });

    it('returns empty data for page beyond last page', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        actingAs($this->superAdmin);

        Category::factory()->count(5)->create();

        actingAs($this->superAdmin)
            ->get(route('activity-logs.index', ['page' => 999]))
            ->assertInertia(
                fn ($page) => $page
                    ->where('activities.data', [])
            );
    });
});

describe('Permission Assignment', function () {
    it('view_activity_logs permission exists in the seeder', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        $permission = Permission::where('name', 'view_activity_logs')
            ->where('guard_name', 'web')
            ->first();

        expect($permission)->not->toBeNull();
    });

    it('view_activity_logs is NOT assigned to any role', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        $roles = Role::all();

        foreach ($roles as $role) {
            expect($role->hasPermissionTo('view_activity_logs'))
                ->toBeFalse("Role '{$role->name}' should NOT have view_activity_logs");
        }
    });

    it('super_admin cannot view activity logs without explicit permission', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        expect($this->superAdmin->can('view_activity_logs'))->toBeFalse();
    });

    it('super_admin can view activity logs after being granted permission', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        $this->superAdmin->givePermissionTo($this->viewPermission);

        expect($this->superAdmin->can('view_activity_logs'))->toBeTrue();
    });

    it('admin cannot view activity logs without explicit permission', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        expect($this->admin->can('view_activity_logs'))->toBeFalse();
    });

    it('admin can view activity logs after being granted permission', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        $this->admin->givePermissionTo($this->viewPermission);

        expect($this->admin->can('view_activity_logs'))->toBeTrue();
    });
});

describe('Query Performance', function () {
    beforeEach(function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        $this->superAdmin->givePermissionTo($this->viewPermission);
        Activity::query()->delete();
    });

    it('does not run duplicate role queries when loading activity logs', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        $causerUser = User::factory()->create();
        $causerUser->syncRoles('admin');
        activity()->causedBy($causerUser)->log('test adjustment');
        activity()->causedBy($causerUser)->log('test transfer');
        activity()->causedBy($causerUser)->log('test opname');
        activity()->causedBy($causerUser)->log('test purchase');

        DB::enableQueryLog();
        actingAs($this->superAdmin)
            ->get(route('activity-logs.index'));
        $queries = DB::getQueryLog();

        $duplicateQueries = collect($queries)
            ->filter(fn ($query) => str_contains($query['query'], 'model_has_roles'))
            ->groupBy(fn ($query) => $query['query'].json_encode($query['bindings']))
            ->filter(fn ($group) => $group->count() > 1);

        expect($duplicateQueries)->toHaveCount(0);
    });

    it('does not run duplicate role queries on show page', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        $causerUser = User::factory()->create();
        $causerUser->syncRoles('warehouse');
        $activity = activity()->causedBy($causerUser)->log('show test activity');

        DB::enableQueryLog();
        actingAs($this->superAdmin)
            ->get(route('activity-logs.show', $activity));
        $queries = DB::getQueryLog();

        $duplicateQueries = collect($queries)
            ->filter(fn ($query) => str_contains($query['query'], 'model_has_roles'))
            ->groupBy(fn ($query) => $query['query'].json_encode($query['bindings']))
            ->filter(fn ($group) => $group->count() > 1);

        expect($duplicateQueries)->toHaveCount(0);
    });
});

describe('Caching', function () {
    beforeEach(function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        $this->superAdmin->givePermissionTo($this->viewPermission);
        Activity::query()->delete();
    });

    it('caches events and logNames via Cache::remember', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        actingAs($this->superAdmin);

        Category::factory()->create(['name' => 'Cache Test']);

        $firstResponse = actingAs($this->superAdmin)
            ->get(route('activity-logs.index'));
        $firstResponse->assertSuccessful();

        expect(Cache::has('activity_log_events_'))->toBeTrue();
        expect(Cache::has('activity_log_names_'))->toBeTrue();

        $cachedEvents = Cache::get('activity_log_events_');
        expect($cachedEvents)->toBeObject();
        expect($cachedEvents->contains('created'))->toBeTrue();
    });

    it('skips distinct queries on subsequent requests when cache is warm', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        actingAs($this->superAdmin);

        Category::factory()->create(['name' => 'Warm Cache Test']);

        actingAs($this->superAdmin)
            ->get(route('activity-logs.index'))
            ->assertSuccessful();

        DB::enableQueryLog();
        actingAs($this->superAdmin)
            ->get(route('activity-logs.index'));

        $distinctQueries = collect(DB::getQueryLog())
            ->filter(fn ($q) => str_contains(strtolower($q['query']), 'distinct'))
            ->count();

        DB::disableQueryLog();

        expect($distinctQueries)->toBe(0);
    });

    it('returns correct distinct events', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        actingAs($this->superAdmin);

        Category::factory()->create(['name' => 'Cat 1']);
        $cat = Category::factory()->create(['name' => 'Cat 2']);
        $cat->update(['name' => 'Cat 2 Updated']);
        Category::factory()->create(['name' => 'Cat 3'])->delete();

        actingAs($this->superAdmin)
            ->get(route('activity-logs.index'))
            ->assertInertia(
                fn ($page) => $page
                    ->has('events')
                    ->where('events', fn ($events) => $events->contains('created')
                        && $events->contains('updated')
                        && $events->contains('deleted'))
            );
    });

    it('returns correct distinct logNames', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        actingAs($this->superAdmin);

        Category::factory()->create();
        activity()->log('manual log event');

        actingAs($this->superAdmin)
            ->get(route('activity-logs.index'))
            ->assertInertia(
                fn ($page) => $page
                    ->has('logNames')
                    ->where('logNames', fn ($names) => $names->contains('default'))
            );
    });

    it('returns empty arrays when no activities exist', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        actingAs($this->superAdmin);

        actingAs($this->superAdmin)
            ->get(route('activity-logs.index'))
            ->assertInertia(
                fn ($page) => $page
                    ->where('events', [])
                    ->where('logNames', [])
            );
    });

    it('filters work correctly with cached values', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        actingAs($this->superAdmin);

        Category::factory()->create(['name' => 'Test Filter']);
        Activity::query()->delete();
        Category::factory()->create(['name' => 'Filter Me']);

        actingAs($this->superAdmin)
            ->get(route('activity-logs.index', ['event' => 'created', 'log_name' => 'default']))
            ->assertSuccessful()
            ->assertInertia(
                fn ($page) => $page
                    ->where('filters.event', 'created')
                    ->where('filters.log_name', 'default')
                    ->has('activities.data')
            );
    });
});

describe('Edge Cases', function () {
    beforeEach(function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        $this->superAdmin->givePermissionTo($this->viewPermission);
        Activity::query()->delete();
    });

    it('handles request with no filters gracefully', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        actingAs($this->superAdmin)
            ->get(route('activity-logs.index'))
            ->assertSuccessful()
            ->assertInertia(
                fn ($page) => $page
                    ->where('filters.search', null)
                    ->where('filters.event', null)
                    ->where('filters.log_name', null)
            );
    });

    it('does not throw on special characters in search', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        actingAs($this->superAdmin);

        actingAs($this->superAdmin)
            ->get(route('activity-logs.index', ['search' => '%test%']))
            ->assertSuccessful();
    });

    it('logs activity for multiple different models', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        actingAs($this->superAdmin);

        Category::factory()->create();
        Unit::factory()->create();

        $activities = Activity::all();
        $subjectTypes = $activities->pluck('subject_type')->unique()->sort()->values()->toArray();

        expect($activities)->toHaveCount(2);
        expect($subjectTypes)->toEqual([Category::class, Unit::class]);
    });

    it('shows activities ordered by newest first', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        actingAs($this->superAdmin);

        $category1 = Category::factory()->create(['name' => 'First']);
        sleep(1);
        $category2 = Category::factory()->create(['name' => 'Second']);

        actingAs($this->superAdmin)
            ->get(route('activity-logs.index'))
            ->assertInertia(
                fn ($page) => $page
                    ->where('activities.data.0.subject_id', $category2->id)
                    ->where('activities.data.1.subject_id', $category1->id)
            );
    });
});

describe('Causer Data', function () {
    beforeEach(function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        $this->superAdmin->givePermissionTo($this->viewPermission);
        Activity::query()->delete();
    });

    it('includes causer name and email in index response', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        activity()->causedBy($this->superAdmin)->log('test activity');

        actingAs($this->superAdmin)
            ->get(route('activity-logs.index'))
            ->assertInertia(
                fn ($page) => $page
                    ->has('activities.data.0.causer')
                    ->where('activities.data.0.causer.name', $this->superAdmin->name)
                    ->where('activities.data.0.causer.email', $this->superAdmin->email)
            );
    });

    it('does not include role attribute in causer data', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        activity()->causedBy($this->superAdmin)->log('test activity');

        actingAs($this->superAdmin)
            ->get(route('activity-logs.index'))
            ->assertInertia(
                fn ($page) => $page
                    ->missing('activities.data.0.causer.role')
            );
    });

    it('handles system activities with null causer', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        activity()->log('system generated activity');

        actingAs($this->superAdmin)
            ->get(route('activity-logs.index'))
            ->assertInertia(
                fn ($page) => $page
                    ->where('activities.data.0.causer', null)
            );
    });
});

describe('Show Page', function () {
    beforeEach(function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        $this->superAdmin->givePermissionTo($this->viewPermission);
        Activity::query()->delete();
    });

    it('renders the correct Inertia component', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        $activity = activity()->causedBy($this->superAdmin)->log('show page test');

        actingAs($this->superAdmin)
            ->get(route('activity-logs.show', $activity))
            ->assertInertia(fn ($page) => $page->component('ActivityLog/Show'));
    });

    it('provides activity data structure on show page', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        $activity = activity()->causedBy($this->superAdmin)->log('show data test');

        actingAs($this->superAdmin)
            ->get(route('activity-logs.show', $activity))
            ->assertInertia(
                fn ($page) => $page
                    ->has('activity')
                    ->where('activity.id', $activity->id)
                    ->where('activity.description', 'show data test')
                    ->where('activity.causer.name', $this->superAdmin->name)
                    ->where('activity.causer.email', $this->superAdmin->email)
            );
    });

    it('returns 403 for activity from another company', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        $company = CompanyFactory::new()->create();
        $otherUser = User::factory()->create(['company_id' => $company->id]);
        $activity = activity()->causedBy($otherUser)->log('other company activity');

        actingAs($this->superAdmin)
            ->get(route('activity-logs.show', $activity))
            ->assertForbidden();
    });

    it('handles system activity with null causer on show page', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        $activity = activity()->log('system activity show');

        actingAs($this->superAdmin)
            ->get(route('activity-logs.show', $activity))
            ->assertInertia(
                fn ($page) => $page
                    ->where('activity.causer', null)
            );
    });
});

describe('No Duplicate Role Queries', function () {
    beforeEach(function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        $this->superAdmin->givePermissionTo($this->viewPermission);
        Activity::query()->delete();
    });

    it('runs only one role query (from middleware) on index page with auth user as causer', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        activity()->causedBy($this->superAdmin)->log('activity by auth user');
        activity()->causedBy($this->superAdmin)->log('another activity');

        DB::enableQueryLog();
        actingAs($this->superAdmin)
            ->get(route('activity-logs.index'));
        $queries = DB::getQueryLog();

        $roleQueries = collect($queries)
            ->filter(fn ($query) => str_contains($query['query'], 'model_has_roles'));

        expect($roleQueries)->toHaveCount(1);
    });

    it('runs only one role query (from middleware) on show page', function () {
        /** @var TestCase&object{viewPermission:Permission, superAdmin:User, admin:User, cashier:User} $this */
        $activity = activity()->causedBy($this->superAdmin)->log('show no role query');

        DB::enableQueryLog();
        actingAs($this->superAdmin)
            ->get(route('activity-logs.show', $activity));
        $queries = DB::getQueryLog();

        $roleQueries = collect($queries)
            ->filter(fn ($query) => str_contains($query['query'], 'model_has_roles'));

        expect($roleQueries)->toHaveCount(1);
    });
});
