<?php

use App\Models\Company;
use App\Models\User;
use App\Services\General\QueueWorkerLogService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\seed;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    seed(RoleAndPermissionSeeder::class);
});

// ---------------------------------------------------------------------------
// Route registration
// ---------------------------------------------------------------------------

it('has a valid named route', function () {
    expect(route('platform.owner.queue-worker-logs.index'))->toBeString();
});

// ---------------------------------------------------------------------------
// Authentication
// ---------------------------------------------------------------------------

it('redirects unauthenticated guests to the login page', function () {
    get(route('platform.owner.queue-worker-logs.index'))
        ->assertRedirect(route('login'));
});

// ---------------------------------------------------------------------------
// Access control — platform_owner is the ONLY allowed role
// ---------------------------------------------------------------------------

it('allows platform_owner to access the queue worker log page', function () {
    actingAs(createPlatformOwner())
        ->get(route('platform.owner.queue-worker-logs.index'))
        ->assertOk();
});

it('forbids tenant roles from accessing the queue worker log page', function (string $role) {
    $company = Company::factory()->create();

    /** @var User $user */
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->syncRoles($role);

    actingAs($user)
        ->get(route('platform.owner.queue-worker-logs.index'))
        ->assertForbidden();
})->with(['super_admin', 'admin', 'cashier', 'warehouse']);

// ---------------------------------------------------------------------------
// Inertia response
// ---------------------------------------------------------------------------

it('renders the correct Inertia page component', function () {
    actingAs(createPlatformOwner())
        ->get(route('platform.owner.queue-worker-logs.index'))
        ->assertInertia(fn ($page) => $page
            ->component('Platform/Owner/QueueWorkerLog/Index')
        );
});

it('passes stats, lines, and filters props', function () {
    actingAs(createPlatformOwner())
        ->get(route('platform.owner.queue-worker-logs.index'))
        ->assertInertia(fn ($page) => $page
            ->has('stats')
            ->has('lines')
            ->has('filters')
        );
});

it('passes null stats and empty lines when the log file does not exist', function () {
    // Ensure no log file is present
    $path = storage_path('logs/queue-worker.log');
    if (file_exists($path)) {
        unlink($path);
    }

    actingAs(createPlatformOwner())
        ->get(route('platform.owner.queue-worker-logs.index'))
        ->assertInertia(fn ($page) => $page
            ->where('stats', null)
            ->where('lines', [])
        );
});

// ---------------------------------------------------------------------------
// Query filters are forwarded to props
// ---------------------------------------------------------------------------

it('reflects search filter in the filters prop', function () {
    actingAs(createPlatformOwner())
        ->get(route('platform.owner.queue-worker-logs.index', ['search' => 'error']))
        ->assertInertia(fn ($page) => $page
            ->where('filters.search', 'error')
        );
});

it('reflects level filter in the filters prop', function () {
    actingAs(createPlatformOwner())
        ->get(route('platform.owner.queue-worker-logs.index', ['level' => 'warning']))
        ->assertInertia(fn ($page) => $page
            ->where('filters.level', 'warning')
        );
});

it('ignores unknown query parameters from filters prop', function () {
    actingAs(createPlatformOwner())
        ->get(route('platform.owner.queue-worker-logs.index', ['unknown' => 'x']))
        ->assertInertia(fn ($page) => $page
            ->missing('filters.unknown')
        );
});

// ---------------------------------------------------------------------------
// Permission seeder — view_queue_worker_logs must NOT exist
// ---------------------------------------------------------------------------

it('does not register a view_queue_worker_logs permission', function () {
    expect(Permission::where('name', 'view_queue_worker_logs')->exists())
        ->toBeFalse();
});

it('does not assign view_queue_worker_logs to the admin role', function () {
    $adminRole = Role::findByName('admin', 'web');

    expect($adminRole->permissions->pluck('name')->contains('view_queue_worker_logs'))
        ->toBeFalse();
});

// ---------------------------------------------------------------------------
// QueueWorkerLogService — unit-level coverage
// ---------------------------------------------------------------------------

it('returns null stats and empty lines when the log file is missing', function () {
    $path = storage_path('logs/queue-worker.log');
    if (file_exists($path)) {
        unlink($path);
    }

    $result = app(QueueWorkerLogService::class)->getLogData();

    expect($result['stats'])->toBeNull()
        ->and($result['lines'])->toBe([]);
});

it('returns stats with file metadata when the log file exists', function () {
    $path = storage_path('logs/queue-worker.log');
    file_put_contents($path, "[2024-01-01] INFO: Job processed\n[2024-01-01] INFO: Job processed\n");

    $result = app(QueueWorkerLogService::class)->getLogData();

    expect($result['stats'])->toMatchArray([
        'file' => 'logs/queue-worker.log',
    ])
        ->and($result['stats']['lines'])->toBe(2)
        ->and($result['stats']['size'])->toBeString()
        ->and($result['stats']['modified'])->toBeString();

    unlink($path);
});

it('detects error level from log lines', function () {
    $service = app(QueueWorkerLogService::class);

    $lines = $service->parseLines([
        '[2024-01-01 00:00:00] local.ERROR: Something failed',
        '[2024-01-01 00:00:00] local.CRITICAL: DB down',
    ]);

    expect($lines->every(fn ($l) => $l['level'] === 'error'))->toBeTrue();
});

it('detects warning level from log lines', function () {
    $service = app(QueueWorkerLogService::class);

    $lines = $service->parseLines([
        '[2024-01-01 00:00:00] local.WARNING: Slow query detected',
    ]);

    expect($lines->first()['level'])->toBe('warning');
});

it('defaults to info level for unrecognised log lines', function () {
    $service = app(QueueWorkerLogService::class);

    $lines = $service->parseLines([
        '[2024-01-01 00:00:00] local.INFO: Queue worker started',
        'Plain text line with no level marker',
    ]);

    expect($lines->every(fn ($l) => $l['level'] === 'info'))->toBeTrue();
});

it('filters lines by case-insensitive search query', function () {
    $service = app(QueueWorkerLogService::class);

    $lines = $service->parseLines([
        '[2024-01-01] INFO: SendInvoiceJob processed',
        '[2024-01-01] ERROR: ContactInquiryMail failed',
        '[2024-01-01] INFO: Queue idle',
    ]);

    $filtered = $service->filterBySearch($lines, 'MAIL');

    expect($filtered)->toHaveCount(1)
        ->and($filtered->first()['text'])->toContain('ContactInquiryMail');
});

it('filters lines by level', function () {
    $service = app(QueueWorkerLogService::class);

    $lines = $service->parseLines([
        '[2024-01-01] local.ERROR: Job failed',
        '[2024-01-01] local.INFO: Job processed',
        '[2024-01-01] local.WARNING: Slow job',
    ]);

    $errors = $service->filterByLevel($lines, 'error');

    expect($errors)->toHaveCount(1)
        ->and($errors->first()['level'])->toBe('error');
});

it('returns empty collection when no lines match the filter', function () {
    $service = app(QueueWorkerLogService::class);

    $lines = $service->parseLines(['[2024-01-01] INFO: All good']);

    $result = $service->filterBySearch($lines, 'nonexistent-string-xyz');

    expect($result)->toHaveCount(0);
});

it('applies search and level filters together via getLogData', function () {
    $path = storage_path('logs/queue-worker.log');
    file_put_contents($path, implode("\n", [
        '[2024-01-01] local.ERROR: SendInvoiceJob failed',
        '[2024-01-01] local.ERROR: ContactMailJob failed',
        '[2024-01-01] local.INFO: Queue worker started',
    ]));

    $result = app(QueueWorkerLogService::class)->getLogData('Invoice', 'error');

    expect($result['lines'])->toHaveCount(1)
        ->and($result['lines'][0]['text'])->toContain('SendInvoiceJob');

    unlink($path);
});
