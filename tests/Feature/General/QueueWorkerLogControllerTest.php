<?php

use App\Models\Company;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\seed;

uses(LazilyRefreshDatabase::class);

/** @var callable(): string $logPath */
$logPath = fn () => storage_path('logs/queue-worker.log');

beforeEach(function () use ($logPath) {
    seed(RoleAndPermissionSeeder::class);

    if (File::exists($logPath())) {
        File::delete($logPath());
    }
});

afterEach(function () use ($logPath) {
    if (File::exists($logPath())) {
        File::delete($logPath());
    }
});

// ---------------------------------------------------------------------------
// Access Control
// ---------------------------------------------------------------------------

describe('Access Control', function () {
    it('redirects unauthenticated users to login', function () {
        get(route('platform.owner.queue-worker-logs.index'))
            ->assertRedirect(route('login'));
    });

    it('forbids tenant admin users', function () {
        $company = Company::factory()->create();

        /** @var User $admin */
        $admin = User::factory()->create(['company_id' => $company->id]);
        $admin->syncRoles('admin');

        actingAs($admin)
            ->get(route('platform.owner.queue-worker-logs.index'))
            ->assertForbidden();
    });

    it('forbids cashier users', function () {
        $company = Company::factory()->create();

        /** @var User $cashier */
        $cashier = User::factory()->create(['company_id' => $company->id]);
        $cashier->syncRoles('cashier');

        actingAs($cashier)
            ->get(route('platform.owner.queue-worker-logs.index'))
            ->assertForbidden();
    });

    it('forbids warehouse users', function () {
        $company = Company::factory()->create();

        /** @var User $warehouse */
        $warehouse = User::factory()->create(['company_id' => $company->id]);
        $warehouse->syncRoles('warehouse');

        actingAs($warehouse)
            ->get(route('platform.owner.queue-worker-logs.index'))
            ->assertForbidden();
    });

    it('allows platform_owner to access', function () {
        actingAs(createPlatformOwner())
            ->get(route('platform.owner.queue-worker-logs.index'))
            ->assertOk();
    });
});

// ---------------------------------------------------------------------------
// Page Rendering
// ---------------------------------------------------------------------------

describe('Page Rendering', function () {
    it('renders the correct Inertia component', function () {
        actingAs(createPlatformOwner())
            ->get(route('platform.owner.queue-worker-logs.index'))
            ->assertInertia(fn ($page) => $page->component('Platform/Owner/QueueWorkerLog/Index'));
    });

    it('provides null stats when log file does not exist', function () {
        actingAs(createPlatformOwner())
            ->get(route('platform.owner.queue-worker-logs.index'))
            ->assertInertia(fn ($page) => $page
                ->where('stats', null)
                ->has('lines')
            );
    });

    it('provides empty lines array when log file does not exist', function () {
        actingAs(createPlatformOwner())
            ->get(route('platform.owner.queue-worker-logs.index'))
            ->assertInertia(fn ($page) => $page
                ->where('lines', [])
            );
    });
});

// ---------------------------------------------------------------------------
// Log Content Display
// ---------------------------------------------------------------------------

describe('Log Content Display', function () use ($logPath) {
    it('provides stats when log file exists', function () use ($logPath) {
        File::put($logPath(), "[2026-05-08 10:00:00] INFO: Test entry\n");

        actingAs(createPlatformOwner())
            ->get(route('platform.owner.queue-worker-logs.index'))
            ->assertInertia(fn ($page) => $page
                ->where('stats.file', 'logs/queue-worker.log')
                ->where('stats.lines', 1)
            );
    });

    it('correctly counts lines in log file', function () use ($logPath) {
        $content = '';
        for ($i = 1; $i <= 5; $i++) {
            $content .= "[2026-05-08 10:00:0{$i}] INFO: Line {$i}\n";
        }
        File::put($logPath(), $content);

        actingAs(createPlatformOwner())
            ->get(route('platform.owner.queue-worker-logs.index'))
            ->assertInertia(fn ($page) => $page
                ->where('stats.lines', 5)
            );
    });

    it('returns parsed lines with correct level detection', function () use ($logPath) {
        $content = "[2026-05-08 10:00:00] local.INFO: Normal entry\n";
        $content .= "[2026-05-08 10:00:01] local.ERROR: Something failed\n";
        $content .= "[2026-05-08 10:00:02] local.WARNING: Low memory\n";
        File::put($logPath(), $content);

        actingAs(createPlatformOwner())
            ->get(route('platform.owner.queue-worker-logs.index'))
            ->assertInertia(fn ($page) => $page
                ->has('lines', 3)
                ->where('lines.0.level', 'info')
                ->where('lines.1.level', 'error')
                ->where('lines.2.level', 'warning')
            );
    });

    it('detects CRITICAL, ALERT, and EMERGENCY as error level', function () use ($logPath) {
        $content = "[2026-05-08 10:00:00] local.CRITICAL: System crash\n";
        $content .= "[2026-05-08 10:00:01] local.ALERT: High alert\n";
        $content .= "[2026-05-08 10:00:02] local.EMERGENCY: Fatal\n";
        File::put($logPath(), $content);

        actingAs(createPlatformOwner())
            ->get(route('platform.owner.queue-worker-logs.index'))
            ->assertInertia(fn ($page) => $page
                ->where('lines.0.level', 'error')
                ->where('lines.1.level', 'error')
                ->where('lines.2.level', 'error')
            );
    });

    it('provides filesize in human-readable format', function () use ($logPath) {
        File::put($logPath(), str_repeat('x', 2048));

        actingAs(createPlatformOwner())
            ->get(route('platform.owner.queue-worker-logs.index'))
            ->assertInertia(fn ($page) => $page
                ->where('stats.size', fn ($size) => str_contains($size, 'KB'))
            );
    });
});

// ---------------------------------------------------------------------------
// Filtering
// ---------------------------------------------------------------------------

describe('Filtering', function () use ($logPath) {
    it('filters lines by search query', function () use ($logPath) {
        $content = "[2026-05-08 10:00:00] INFO: User created\n";
        $content .= "[2026-05-08 10:00:01] INFO: Order processed\n";
        $content .= "[2026-05-08 10:00:02] INFO: User updated\n";
        File::put($logPath(), $content);

        actingAs(createPlatformOwner())
            ->get(route('platform.owner.queue-worker-logs.index', ['search' => 'User']))
            ->assertInertia(fn ($page) => $page->has('lines', 2));
    });

    it('filters lines by error level', function () use ($logPath) {
        $content = "[2026-05-08 10:00:00] local.INFO: Normal\n";
        $content .= "[2026-05-08 10:00:01] local.ERROR: Bad\n";
        $content .= "[2026-05-08 10:00:02] local.INFO: Normal again\n";
        File::put($logPath(), $content);

        actingAs(createPlatformOwner())
            ->get(route('platform.owner.queue-worker-logs.index', ['level' => 'error']))
            ->assertInertia(fn ($page) => $page
                ->has('lines', 1)
                ->where('lines.0.level', 'error')
            );
    });

    it('filters lines by warning level', function () use ($logPath) {
        $content = "[2026-05-08 10:00:00] local.INFO: Normal\n";
        $content .= "[2026-05-08 10:00:01] local.WARNING: Warning entry\n";
        $content .= "[2026-05-08 10:00:02] local.ERROR: Error entry\n";
        $content .= "[2026-05-08 10:00:03] local.WARN: Another warning\n";
        File::put($logPath(), $content);

        actingAs(createPlatformOwner())
            ->get(route('platform.owner.queue-worker-logs.index', ['level' => 'warning']))
            ->assertInertia(fn ($page) => $page->has('lines', 2));
    });

    it('filters lines by info level', function () use ($logPath) {
        $content = "[2026-05-08 10:00:00] local.INFO: Normal\n";
        $content .= "[2026-05-08 10:00:01] local.ERROR: Bad\n";
        File::put($logPath(), $content);

        actingAs(createPlatformOwner())
            ->get(route('platform.owner.queue-worker-logs.index', ['level' => 'info']))
            ->assertInertia(fn ($page) => $page
                ->has('lines', 1)
                ->where('lines.0.level', 'info')
            );
    });

    it('combines search and level filters', function () use ($logPath) {
        $content = "[2026-05-08 10:00:00] local.ERROR: Database connection failed\n";
        $content .= "[2026-05-08 10:00:01] local.ERROR: Queue timeout\n";
        $content .= "[2026-05-08 10:00:02] local.INFO: Database query completed\n";
        File::put($logPath(), $content);

        actingAs(createPlatformOwner())
            ->get(route('platform.owner.queue-worker-logs.index', ['search' => 'Database', 'level' => 'error']))
            ->assertInertia(fn ($page) => $page
                ->has('lines', 1)
                ->where('lines.0.text', fn ($text) => str_contains($text, 'connection failed'))
            );
    });

    it('returns empty lines when search finds nothing', function () use ($logPath) {
        File::put($logPath(), "[2026-05-08 10:00:00] INFO: Normal entry\n");

        actingAs(createPlatformOwner())
            ->get(route('platform.owner.queue-worker-logs.index', ['search' => 'NonExistent']))
            ->assertInertia(fn ($page) => $page->where('lines', []));
    });

    it('returns empty lines when level filter finds nothing', function () use ($logPath) {
        File::put($logPath(), "[2026-05-08 10:00:00] INFO: Normal entry\n");

        actingAs(createPlatformOwner())
            ->get(route('platform.owner.queue-worker-logs.index', ['level' => 'error']))
            ->assertInertia(fn ($page) => $page->where('lines', []));
    });

    it('passes filter values back to the view', function () use ($logPath) {
        File::put($logPath(), "[2026-05-08 10:00:00] INFO: Test\n");

        actingAs(createPlatformOwner())
            ->get(route('platform.owner.queue-worker-logs.index', ['search' => 'Test', 'level' => 'info']))
            ->assertInertia(fn ($page) => $page
                ->where('filters.search', 'Test')
                ->where('filters.level', 'info')
            );
    });
});

// ---------------------------------------------------------------------------
// Edge Cases
// ---------------------------------------------------------------------------

describe('Edge Cases', function () use ($logPath) {
    it('handles log file with only newlines', function () use ($logPath) {
        File::put($logPath(), "\n\n");

        actingAs(createPlatformOwner())
            ->get(route('platform.owner.queue-worker-logs.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.lines', 0)
            );
    });

    it('handles very long log lines', function () use ($logPath) {
        File::put($logPath(), '[2026-05-08 10:00:00] INFO: '.str_repeat('A', 5000)."\n");

        actingAs(createPlatformOwner())
            ->get(route('platform.owner.queue-worker-logs.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('lines', 1));
    });

    it('handles large log files with many lines', function () use ($logPath) {
        $content = '';
        for ($i = 1; $i <= 500; $i++) {
            $content .= "[2026-05-08 10:00:00] INFO: Line {$i}\n";
        }
        File::put($logPath(), $content);

        actingAs(createPlatformOwner())
            ->get(route('platform.owner.queue-worker-logs.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.lines', 500)
                ->has('lines', 500)
            );
    });

    it('logs INFO lines without ERROR/WARNING keyword are detected as info', function () use ($logPath) {
        File::put($logPath(), "[2026-05-08 10:00:00] local.INFO: Processing job App\\Jobs\\CalculateRevenue\n");

        actingAs(createPlatformOwner())
            ->get(route('platform.owner.queue-worker-logs.index'))
            ->assertInertia(fn ($page) => $page
                ->where('lines.0.level', 'info')
            );
    });
});
