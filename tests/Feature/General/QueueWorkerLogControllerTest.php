<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    /** @var TestCase&object{admin:User, cashier:User, logPath:string} $this */
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->cashier = User::factory()->create(['role' => 'cashier']);
    $this->logPath = storage_path('logs/queue-worker.log');

    if (File::exists($this->logPath)) {
        File::delete($this->logPath);
    }
});

afterEach(function () {
    /** @var TestCase&object{admin:User, cashier:User, logPath:string} $this */
    if (File::exists($this->logPath)) {
        File::delete($this->logPath);
    }
});

describe('Access Control', function () {
    it('redirects unauthenticated users to login', function () {
        /** @var TestCase&object{admin:User, cashier:User, logPath:string} $this */
        get(route('queue-worker-logs.index'))
            ->assertRedirect(route('login'));
    });

    it('forbids cashier users', function () {
        /** @var TestCase&object{admin:User, cashier:User, logPath:string} $this */
        actingAs($this->cashier)
            ->get(route('queue-worker-logs.index'))
            ->assertForbidden();
    });

    it('forbids warehouse users', function () {
        /** @var TestCase&object{admin:User, cashier:User, logPath:string} $this */
        $warehouse = User::factory()->create(['role' => 'warehouse']);

        actingAs($warehouse)
            ->get(route('queue-worker-logs.index'))
            ->assertForbidden();
    });

    it('allows admin users to access', function () {
        /** @var TestCase&object{admin:User, cashier:User, logPath:string} $this */
        actingAs($this->admin)
            ->get(route('queue-worker-logs.index'))
            ->assertSuccessful();
    });
});

describe('Page Rendering', function () {
    it('renders the correct Inertia component', function () {
        /** @var TestCase&object{admin:User, cashier:User, logPath:string} $this */
        actingAs($this->admin)
            ->get(route('queue-worker-logs.index'))
            ->assertInertia(fn ($page) => $page->component('QueueWorkerLog/Index'));
    });

    it('provides null stats when log file does not exist', function () {
        /** @var TestCase&object{admin:User, cashier:User, logPath:string} $this */
        actingAs($this->admin)
            ->get(route('queue-worker-logs.index'))
            ->assertInertia(fn ($page) => $page
                ->where('stats', null)
                ->has('lines')
            );
    });

    it('provides empty lines array when log file does not exist', function () {
        /** @var TestCase&object{admin:User, cashier:User, logPath:string} $this */
        actingAs($this->admin)
            ->get(route('queue-worker-logs.index'))
            ->assertInertia(fn ($page) => $page
                ->where('lines', [])
            );
    });
});

describe('Log Content Display', function () {
    it('provides stats when log file exists', function () {
        /** @var TestCase&object{admin:User, cashier:User, logPath:string} $this */
        File::put($this->logPath, "[2026-05-08 10:00:00] INFO: Test entry\n");

        actingAs($this->admin)
            ->get(route('queue-worker-logs.index'))
            ->assertInertia(fn ($page) => $page
                ->where('stats.file', 'logs/queue-worker.log')
                ->where('stats.lines', 1)
            );
    });

    it('correctly counts lines in log file', function () {
        /** @var TestCase&object{admin:User, cashier:User, logPath:string} $this */
        $content = '';
        for ($i = 1; $i <= 5; $i++) {
            $content .= "[2026-05-08 10:00:0{$i}] INFO: Line {$i}\n";
        }
        File::put($this->logPath, $content);

        actingAs($this->admin)
            ->get(route('queue-worker-logs.index'))
            ->assertInertia(fn ($page) => $page
                ->where('stats.lines', 5)
            );
    });

    it('returns parsed lines with correct level detection', function () {
        /** @var TestCase&object{admin:User, cashier:User, logPath:string} $this */
        $content = "[2026-05-08 10:00:00] local.INFO: Normal entry\n";
        $content .= "[2026-05-08 10:00:01] local.ERROR: Something failed\n";
        $content .= "[2026-05-08 10:00:02] local.WARNING: Low memory\n";
        File::put($this->logPath, $content);

        actingAs($this->admin)
            ->get(route('queue-worker-logs.index'))
            ->assertInertia(fn ($page) => $page
                ->has('lines', 3)
                ->where('lines.0.level', 'info')
                ->where('lines.1.level', 'error')
                ->where('lines.2.level', 'warning')
            );
    });

    it('detects CRITICAL, ALERT, and EMERGENCY as error level', function () {
        /** @var TestCase&object{admin:User, cashier:User, logPath:string} $this */
        $content = "[2026-05-08 10:00:00] local.CRITICAL: System crash\n";
        $content .= "[2026-05-08 10:00:01] local.ALERT: High alert\n";
        $content .= "[2026-05-08 10:00:02] local.EMERGENCY: Fatal\n";
        File::put($this->logPath, $content);

        actingAs($this->admin)
            ->get(route('queue-worker-logs.index'))
            ->assertInertia(fn ($page) => $page
                ->where('lines.0.level', 'error')
                ->where('lines.1.level', 'error')
                ->where('lines.2.level', 'error')
            );
    });

    it('provides filesize in human-readable format', function () {
        /** @var TestCase&object{admin:User, cashier:User, logPath:string} $this */
        $content = str_repeat('x', 2048);
        File::put($this->logPath, $content);

        actingAs($this->admin)
            ->get(route('queue-worker-logs.index'))
            ->assertInertia(fn ($page) => $page
                ->where('stats.size', fn ($size) => str_contains($size, 'KB'))
            );
    });
});

describe('Filtering', function () {
    it('filters lines by search query', function () {
        /** @var TestCase&object{admin:User, cashier:User, logPath:string} $this */
        $content = "[2026-05-08 10:00:00] INFO: User created\n";
        $content .= "[2026-05-08 10:00:01] INFO: Order processed\n";
        $content .= "[2026-05-08 10:00:02] INFO: User updated\n";
        File::put($this->logPath, $content);

        actingAs($this->admin)
            ->get(route('queue-worker-logs.index', ['search' => 'User']))
            ->assertInertia(fn ($page) => $page
                ->has('lines', 2)
            );
    });

    it('filters lines by error level', function () {
        /** @var TestCase&object{admin:User, cashier:User, logPath:string} $this */
        $content = "[2026-05-08 10:00:00] local.INFO: Normal\n";
        $content .= "[2026-05-08 10:00:01] local.ERROR: Bad\n";
        $content .= "[2026-05-08 10:00:02] local.INFO: Normal again\n";
        File::put($this->logPath, $content);

        actingAs($this->admin)
            ->get(route('queue-worker-logs.index', ['level' => 'error']))
            ->assertInertia(fn ($page) => $page
                ->has('lines', 1)
                ->where('lines.0.level', 'error')
            );
    });

    it('filters lines by warning level', function () {
        /** @var TestCase&object{admin:User, cashier:User, logPath:string} $this */
        $content = "[2026-05-08 10:00:00] local.INFO: Normal\n";
        $content .= "[2026-05-08 10:00:01] local.WARNING: Warning entry\n";
        $content .= "[2026-05-08 10:00:02] local.ERROR: Error entry\n";
        $content .= "[2026-05-08 10:00:03] local.WARN: Another warning\n";
        File::put($this->logPath, $content);

        actingAs($this->admin)
            ->get(route('queue-worker-logs.index', ['level' => 'warning']))
            ->assertInertia(fn ($page) => $page
                ->has('lines', 2)
            );
    });

    it('filters lines by info level', function () {
        /** @var TestCase&object{admin:User, cashier:User, logPath:string} $this */
        $content = "[2026-05-08 10:00:00] local.INFO: Normal\n";
        $content .= "[2026-05-08 10:00:01] local.ERROR: Bad\n";
        File::put($this->logPath, $content);

        actingAs($this->admin)
            ->get(route('queue-worker-logs.index', ['level' => 'info']))
            ->assertInertia(fn ($page) => $page
                ->has('lines', 1)
                ->where('lines.0.level', 'info')
            );
    });

    it('combines search and level filters', function () {
        /** @var TestCase&object{admin:User, cashier:User, logPath:string} $this */
        $content = "[2026-05-08 10:00:00] local.ERROR: Database connection failed\n";
        $content .= "[2026-05-08 10:00:01] local.ERROR: Queue timeout\n";
        $content .= "[2026-05-08 10:00:02] local.INFO: Database query completed\n";
        File::put($this->logPath, $content);

        actingAs($this->admin)
            ->get(route('queue-worker-logs.index', ['search' => 'Database', 'level' => 'error']))
            ->assertInertia(fn ($page) => $page
                ->has('lines', 1)
                ->where('lines.0.text', fn ($text) => str_contains($text, 'connection failed'))
            );
    });

    it('returns empty lines when search finds nothing', function () {
        /** @var TestCase&object{admin:User, cashier:User, logPath:string} $this */
        $content = "[2026-05-08 10:00:00] INFO: Normal entry\n";
        File::put($this->logPath, $content);

        actingAs($this->admin)
            ->get(route('queue-worker-logs.index', ['search' => 'NonExistent']))
            ->assertInertia(fn ($page) => $page
                ->where('lines', [])
            );
    });

    it('returns empty lines when level filter finds nothing', function () {
        /** @var TestCase&object{admin:User, cashier:User, logPath:string} $this */
        $content = "[2026-05-08 10:00:00] INFO: Normal entry\n";
        File::put($this->logPath, $content);

        actingAs($this->admin)
            ->get(route('queue-worker-logs.index', ['level' => 'error']))
            ->assertInertia(fn ($page) => $page
                ->where('lines', [])
            );
    });

    it('passes filter values back to the view', function () {
        /** @var TestCase&object{admin:User, cashier:User, logPath:string} $this */
        File::put($this->logPath, "[2026-05-08 10:00:00] INFO: Test\n");

        actingAs($this->admin)
            ->get(route('queue-worker-logs.index', ['search' => 'Test', 'level' => 'info']))
            ->assertInertia(fn ($page) => $page
                ->where('filters.search', 'Test')
                ->where('filters.level', 'info')
            );
    });
});

describe('Edge Cases', function () {
    it('handles log file with only newlines', function () {
        /** @var TestCase&object{admin:User, cashier:User, logPath:string} $this */
        File::put($this->logPath, "\n\n");

        actingAs($this->admin)
            ->get(route('queue-worker-logs.index'))
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->where('stats.lines', 0)
            );
    });

    it('handles very long log lines', function () {
        /** @var TestCase&object{admin:User, cashier:User, logPath:string} $this */
        $longLine = str_repeat('A', 5000);
        File::put($this->logPath, "[2026-05-08 10:00:00] INFO: {$longLine}\n");

        actingAs($this->admin)
            ->get(route('queue-worker-logs.index'))
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->has('lines', 1)
            );
    });

    it('handles large log files with many lines', function () {
        /** @var TestCase&object{admin:User, cashier:User, logPath:string} $this */
        $content = '';
        for ($i = 1; $i <= 500; $i++) {
            $content .= "[2026-05-08 10:00:00] INFO: Line {$i}\n";
        }
        File::put($this->logPath, $content);

        actingAs($this->admin)
            ->get(route('queue-worker-logs.index'))
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->where('stats.lines', 500)
                ->has('lines', 500)
            );
    });

    it('logs INFO lines without ERROR/WARNING keyword are detected as info', function () {
        /** @var TestCase&object{admin:User, cashier:User, logPath:string} $this */
        $content = "[2026-05-08 10:00:00] local.INFO: Processing job App\\Jobs\\CalculateRevenue\n";
        File::put($this->logPath, $content);

        actingAs($this->admin)
            ->get(route('queue-worker-logs.index'))
            ->assertInertia(fn ($page) => $page
                ->where('lines.0.level', 'info')
            );
    });
});
