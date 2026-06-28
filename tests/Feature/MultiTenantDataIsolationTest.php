<?php

use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

use function Pest\Laravel\actingAs;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    /** @var TestCase&object{companyA: Company, adminA: User, userA: User, companyB: Company, adminB: User, userB: User} $this */
    // Buat plan yang dibutuhkan oleh middleware plan.feature
    Plan::factory()->pemula()->create(); // fallback assignFreeSubscription
    $enterprise = Plan::factory()->enterprise()->create(); // akses semua fitur

    // Company A
    $this->companyA = Company::create(['name' => 'Company A', 'slug' => 'company-a']);
    $this->adminA = User::factory()->create(['company_id' => $this->companyA->id, 'role' => 'admin']);
    $this->userA = User::factory()->create(['company_id' => $this->companyA->id, 'role' => 'cashier']);
    $this->companyA->subscription()->create([
        'plan_id' => $enterprise->id,
        'status' => 'active',
        'starts_at' => now(),
    ]);

    // Company B
    $this->companyB = Company::create(['name' => 'Company B', 'slug' => 'company-b']);
    $this->adminB = User::factory()->create(['company_id' => $this->companyB->id, 'role' => 'admin']);
    $this->userB = User::factory()->create(['company_id' => $this->companyB->id, 'role' => 'cashier']);
    $this->companyB->subscription()->create([
        'plan_id' => $enterprise->id,
        'status' => 'active',
        'starts_at' => now(),
    ]);
});

// ============================================================
// User Permissions — Data Isolation
// ============================================================

describe('User Permissions — Data Isolation', function () {
    it('admin from Company A sees only Company A users', function () {
        /** @var TestCase&object{companyA: Company, adminA: User, userA: User, companyB: Company, adminB: User, userB: User} $this */
        actingAs($this->adminA)
            ->get(route('user-permissions.index'))
            ->assertInertia(
                fn ($page) => $page
                    ->has('users.data', 2)
                    ->where('users.data.0.company_id', $this->companyA->id)
                    ->where('users.data.1.company_id', $this->companyA->id)
            );
    });

    it('admin from Company B sees only Company B users', function () {
        /** @var TestCase&object{companyA: Company, adminA: User, userA: User, companyB: Company, adminB: User, userB: User} $this */
        actingAs($this->adminB)
            ->get(route('user-permissions.index'))
            ->assertInertia(
                fn ($page) => $page
                    ->has('users.data', 2)
                    ->where('users.data.0.company_id', $this->companyB->id)
                    ->where('users.data.1.company_id', $this->companyB->id)
            );
    });

    it('admin cannot edit user from another company', function () {
        /** @var TestCase&object{companyA: Company, adminA: User, userA: User, companyB: Company, adminB: User, userB: User} $this */
        actingAs($this->adminA)
            ->get(route('user-permissions.edit', $this->userB))
            ->assertForbidden();
    });

    it('admin cannot update user from another company', function () {
        /** @var TestCase&object{companyA: Company, adminA: User, userA: User, companyB: Company, adminB: User, userB: User} $this */
        actingAs($this->adminA)
            ->put(route('user-permissions.update', $this->userB), ['permissions' => []])
            ->assertForbidden();
    });
});

// ============================================================
// Activity Logs — Data Isolation
// ============================================================

describe('Activity Logs — Data Isolation', function () {
    beforeEach(function () {
        /** @var TestCase&object{companyA: Company, adminA: User, userA: User, companyB: Company, adminB: User, userB: User, viewPermission: Permission} $this */
        $this->viewPermission = Permission::findByName('view_activity_logs', 'web');

        $this->adminA->givePermissionTo($this->viewPermission);
        $this->adminB->givePermissionTo($this->viewPermission);

        Activity::query()->delete();
    });

    it('admin from Company A sees only Company A activity logs', function () {
        /** @var TestCase&object{companyA: Company, adminA: User, userA: User, companyB: Company, adminB: User, userB: User} $this */
        activity()->causedBy($this->adminA)->log('Company A event');
        activity()->causedBy($this->adminB)->log('Company B event');

        actingAs($this->adminA)
            ->get(route('activity-logs.index'))
            ->assertInertia(
                fn ($page) => $page
                    ->has('activities.data', 1)
                    ->where('activities.data.0.description', 'Company A event')
            );
    });

    it('admin from Company B sees only Company B activity logs', function () {
        /** @var TestCase&object{companyA: Company, adminA: User, userA: User, companyB: Company, adminB: User, userB: User} $this */
        activity()->causedBy($this->adminA)->log('Company A event');
        activity()->causedBy($this->adminB)->log('Company B event');

        actingAs($this->adminB)
            ->get(route('activity-logs.index'))
            ->assertInertia(
                fn ($page) => $page
                    ->has('activities.data', 1)
                    ->where('activities.data.0.description', 'Company B event')
            );
    });

    it('admin from Company A cannot view Company B activity log', function () {
        /** @var TestCase&object{companyA: Company, adminA: User, userA: User, companyB: Company, adminB: User, userB: User} $this */
        activity()->causedBy($this->adminB)->log('Secret Company B event');

        $log = Activity::where('company_id', $this->companyB->id)->first();

        actingAs($this->adminA)
            ->get(route('activity-logs.show', $log))
            ->assertForbidden();
    });

    it('admin from Company A sees Company A activity log show page', function () {
        /** @var TestCase&object{companyA: Company, adminA: User, userA: User, companyB: Company, adminB: User, userB: User} $this */
        activity()->causedBy($this->adminA)->log('Company A event');

        $log = Activity::where('company_id', $this->companyA->id)->first();

        actingAs($this->adminA)
            ->get(route('activity-logs.show', $log))
            ->assertSuccessful();
    });
});

// ============================================================
// File Manager — Data Isolation
// ============================================================

describe('File Manager — Data Isolation', function () {
    it('admin from Company A sees only Company A files', function () {
        /** @var TestCase&object{companyA: Company, adminA: User, userA: User, companyB: Company, adminB: User, userB: User} $this */
        Storage::fake('local');

        $disk = Storage::disk('local');
        $disk->put('uploads/'.$this->companyA->id.'/company-a-file.pdf', 'content a');
        $disk->put('uploads/'.$this->companyB->id.'/company-b-file.pdf', 'content b');

        actingAs($this->adminA)
            ->get(route('file-manager.index'))
            ->assertInertia(
                fn ($page) => $page
                    ->has('files.data', 1)
                    ->where('files.data.0.name', 'company-a-file.pdf')
            );
    });

    it('admin from Company B sees only Company B files', function () {
        /** @var TestCase&object{companyA: Company, adminA: User, userA: User, companyB: Company, adminB: User, userB: User} $this */
        Storage::fake('local');

        $disk = Storage::disk('local');
        $disk->put('uploads/'.$this->companyA->id.'/company-a-file.pdf', 'content a');
        $disk->put('uploads/'.$this->companyB->id.'/company-b-file.pdf', 'content b');

        actingAs($this->adminB)
            ->get(route('file-manager.index'))
            ->assertInertia(
                fn ($page) => $page
                    ->has('files.data', 1)
                    ->where('files.data.0.name', 'company-b-file.pdf')
            );
    });

    it('admin cannot download file from another company', function () {
        /** @var TestCase&object{companyA: Company, adminA: User, userA: User, companyB: Company, adminB: User, userB: User} $this */
        Storage::fake('local');

        $disk = Storage::disk('local');
        $disk->put('uploads/'.$this->companyA->id.'/secret.pdf', 'secret');

        actingAs($this->adminB)
            ->get(route('file-manager.download', ['file' => 'uploads/secret.pdf']))
            ->assertNotFound();
    });

    it('admin cannot delete file from another company', function () {
        /** @var TestCase&object{companyA: Company, adminA: User, userA: User, companyB: Company, adminB: User, userB: User} $this */
        Storage::fake('local');

        $disk = Storage::disk('local');
        $disk->put('uploads/'.$this->companyA->id.'/secret.pdf', 'secret');

        actingAs($this->adminB)
            ->deleteJson(route('file-manager.destroy'), ['file' => 'uploads/secret.pdf'])
            ->assertNotFound();
    });
});
