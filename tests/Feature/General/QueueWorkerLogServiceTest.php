<?php

use App\Services\General\QueueWorkerLogService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

beforeEach(function () {
    /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
    $this->service = new QueueWorkerLogService;
    $this->logPath = storage_path('logs/queue-worker.log');

    if (File::exists($this->logPath)) {
        File::delete($this->logPath);
    }
});

afterEach(function () {
    /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
    if (File::exists($this->logPath)) {
        File::delete($this->logPath);
    }
});

describe('getLogPath', function () {
    it('returns the correct storage path', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        $path = $this->service->getLogPath();

        expect($path)->toBe(storage_path('logs/queue-worker.log'));
    });
});

describe('detectLevel', function () {
    it('detects ERROR as error level', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        expect($this->service->detectLevel('[2026-05-08 10:00:00] local.ERROR: Something failed'))
            ->toBe('error');
    });

    it('detects CRITICAL as error level', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        expect($this->service->detectLevel('[2026-05-08 10:00:00] local.CRITICAL: System crash'))
            ->toBe('error');
    });

    it('detects ALERT as error level', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        expect($this->service->detectLevel('[2026-05-08 10:00:00] local.ALERT: High alert'))
            ->toBe('error');
    });

    it('detects EMERGENCY as error level', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        expect($this->service->detectLevel('[2026-05-08 10:00:00] local.EMERGENCY: Fatal'))
            ->toBe('error');
    });

    it('detects WARNING as warning level', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        expect($this->service->detectLevel('[2026-05-08 10:00:00] local.WARNING: Low memory'))
            ->toBe('warning');
    });

    it('detects WARN as warning level', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        expect($this->service->detectLevel('[2026-05-08 10:00:00] local.WARN: Deprecated'))
            ->toBe('warning');
    });

    it('detects INFO as info level', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        expect($this->service->detectLevel('[2026-05-08 10:00:00] local.INFO: Normal entry'))
            ->toBe('info');
    });

    it('detects lines without known keywords as info level', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        expect($this->service->detectLevel('[2026-05-08 10:00:00] Processing job'))
            ->toBe('info');
    });

    it('detects DEBUG as info level', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        expect($this->service->detectLevel('[2026-05-08 10:00:00] local.DEBUG: Debug info'))
            ->toBe('info');
    });

    it('detects NOTICE as info level', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        expect($this->service->detectLevel('[2026-05-08 10:00:00] local.NOTICE: Notice'))
            ->toBe('info');
    });

    it('does not match level keywords outside the pattern', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        expect($this->service->detectLevel('Some ERROR text but not in pattern'))
            ->toBe('info');
    });
});

describe('formatBytes integration', function () {
    it('delegates to FormatBytes helper for human-readable file size in stats', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        $content = str_repeat('x', 1024);
        File::put($this->logPath, $content);

        $result = $this->service->getLogData();

        expect($result['stats']['size'])->toContain('KB');
    });
});

describe('parseLines', function () {
    it('returns empty collection for empty array', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        $result = $this->service->parseLines([]);

        expect($result)->toBeEmpty();
    });

    it('parses a single line with level detection', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        $result = $this->service->parseLines([
            '[2026-05-08 10:00:00] local.ERROR: Failed',
        ]);

        expect($result)->toHaveCount(1);
        expect($result->first())->toBe([
            'text' => '[2026-05-08 10:00:00] local.ERROR: Failed',
            'level' => 'error',
        ]);
    });

    it('parses multiple lines with different levels', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        $result = $this->service->parseLines([
            '[2026-05-08 10:00:00] local.INFO: Normal',
            '[2026-05-08 10:00:01] local.ERROR: Bad',
            '[2026-05-08 10:00:02] local.WARNING: Warning',
        ]);

        expect($result)->toHaveCount(3);
        expect($result->get(0)['level'])->toBe('info');
        expect($result->get(1)['level'])->toBe('error');
        expect($result->get(2)['level'])->toBe('warning');
    });

    it('preserves original line text in result', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        $line = '[2026-05-08 10:00:00] local.INFO: Processing job App\\Jobs\\CalculateRevenue';

        $result = $this->service->parseLines([$line]);

        expect($result->first()['text'])->toBe($line);
    });

    it('handles empty strings in lines', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        $result = $this->service->parseLines(['']);

        expect($result)->toHaveCount(1);
        expect($result->first()['level'])->toBe('info');
    });
});

describe('filterBySearch', function () {
    it('filters lines containing the search term', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        $lines = $this->service->parseLines([
            'User created',
            'Order processed',
            'User updated',
        ]);

        $result = $this->service->filterBySearch($lines, 'User');

        expect($result)->toHaveCount(2);
    });

    it('is case-insensitive', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        $lines = $this->service->parseLines([
            'USER CREATED',
            'user updated',
            'User deleted',
        ]);

        $result = $this->service->filterBySearch($lines, 'user');

        expect($result)->toHaveCount(3);
    });

    it('returns empty collection when no match found', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        $lines = $this->service->parseLines([
            'Normal entry',
            'Another entry',
        ]);

        $result = $this->service->filterBySearch($lines, 'NonExistent');

        expect($result)->toBeEmpty();
    });

    it('handles empty collection input', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        $lines = collect();

        $result = $this->service->filterBySearch($lines, 'anything');

        expect($result)->toBeEmpty();
    });

    it('re-indexes filtered results', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        $lines = $this->service->parseLines([
            'Apple entry',
            'Banana entry',
            'Apple juice',
        ]);

        $result = $this->service->filterBySearch($lines, 'Apple');

        expect($result->keys()->toArray())->toBe([0, 1]);
    });
});

describe('filterByLevel', function () {
    it('filters lines by error level', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        $lines = $this->service->parseLines([
            '[2026-05-08 10:00:00] local.INFO: Normal',
            '[2026-05-08 10:00:01] local.ERROR: Bad',
            '[2026-05-08 10:00:02] local.ERROR: Worse',
        ]);

        $result = $this->service->filterByLevel($lines, 'error');

        expect($result)->toHaveCount(2);
        expect($result->every(fn ($line) => $line['level'] === 'error'))->toBeTrue();
    });

    it('filters lines by warning level', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        $lines = $this->service->parseLines([
            '[2026-05-08 10:00:00] local.INFO: Normal',
            '[2026-05-08 10:00:01] local.WARNING: Warning',
            '[2026-05-08 10:00:02] local.WARN: Another',
        ]);

        $result = $this->service->filterByLevel($lines, 'warning');

        expect($result)->toHaveCount(2);
    });

    it('filters lines by info level', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        $lines = $this->service->parseLines([
            '[2026-05-08 10:00:00] local.INFO: Normal',
            '[2026-05-08 10:00:01] local.ERROR: Bad',
        ]);

        $result = $this->service->filterByLevel($lines, 'info');

        expect($result)->toHaveCount(1);
        expect($result->first()['level'])->toBe('info');
    });

    it('returns empty collection when no lines match level', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        $lines = $this->service->parseLines([
            '[2026-05-08 10:00:00] local.INFO: Normal',
        ]);

        $result = $this->service->filterByLevel($lines, 'error');

        expect($result)->toBeEmpty();
    });

    it('handles empty collection input', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        $lines = collect();

        $result = $this->service->filterByLevel($lines, 'error');

        expect($result)->toBeEmpty();
    });

    it('re-indexes filtered results', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        $lines = $this->service->parseLines([
            '[2026-05-08 10:00:00] local.ERROR: First',
            '[2026-05-08 10:00:01] local.INFO: Normal',
            '[2026-05-08 10:00:02] local.ERROR: Last',
        ]);

        $result = $this->service->filterByLevel($lines, 'error');

        expect($result->keys()->toArray())->toBe([0, 1]);
    });
});

describe('getLogData', function () {
    it('returns null stats and empty lines when log file does not exist', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        $result = $this->service->getLogData();

        expect($result['stats'])->toBeNull();
        expect($result['lines'])->toBe([]);
    });

    it('returns stats when log file exists', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        File::put($this->logPath, "[2026-05-08 10:00:00] INFO: Test entry\n");

        $result = $this->service->getLogData();

        expect($result['stats'])->not->toBeNull();
        expect($result['stats']['file'])->toBe('logs/queue-worker.log');
        expect($result['stats']['lines'])->toBe(1);
    });

    it('correctly counts lines in log file', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        $content = '';
        for ($i = 1; $i <= 10; $i++) {
            $content .= "[2026-05-08 10:00:0{$i}] INFO: Line {$i}\n";
        }
        File::put($this->logPath, $content);

        $result = $this->service->getLogData();

        expect($result['stats']['lines'])->toBe(10);
        expect($result['lines'])->toHaveCount(10);
    });

    it('returns parsed lines with correct level detection', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        $content = "[2026-05-08 10:00:00] local.INFO: Normal entry\n";
        $content .= "[2026-05-08 10:00:01] local.ERROR: Something failed\n";
        $content .= "[2026-05-08 10:00:02] local.WARNING: Low memory\n";
        File::put($this->logPath, $content);

        $result = $this->service->getLogData();

        expect($result['lines'])->toHaveCount(3);
        expect($result['lines'][0]['level'])->toBe('info');
        expect($result['lines'][1]['level'])->toBe('error');
        expect($result['lines'][2]['level'])->toBe('warning');
    });

    it('detects CRITICAL, ALERT, and EMERGENCY as error level', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        $content = "[2026-05-08 10:00:00] local.CRITICAL: System crash\n";
        $content .= "[2026-05-08 10:00:01] local.ALERT: High alert\n";
        $content .= "[2026-05-08 10:00:02] local.EMERGENCY: Fatal\n";
        File::put($this->logPath, $content);

        $result = $this->service->getLogData();

        expect($result['lines'])->toHaveCount(3);
        expect($result['lines'][0]['level'])->toBe('error');
        expect($result['lines'][1]['level'])->toBe('error');
        expect($result['lines'][2]['level'])->toBe('error');
    });

    it('detects WARN as warning level', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        $content = "[2026-05-08 10:00:00] local.WARN: Deprecated method\n";
        File::put($this->logPath, $content);

        $result = $this->service->getLogData();

        expect($result['lines'][0]['level'])->toBe('warning');
    });

    it('provides human-readable file size', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        $content = str_repeat('x', 1024);
        File::put($this->logPath, $content);

        $result = $this->service->getLogData();

        expect($result['stats']['size'])->toContain('KB');
    });

    it('provides last modified date', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        File::put($this->logPath, "test\n");

        $result = $this->service->getLogData();

        expect($result['stats']['modified'])->not->toBeEmpty();
    });

    it('filters by search query', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        $content = "[2026-05-08 10:00:00] INFO: User created\n";
        $content .= "[2026-05-08 10:00:01] INFO: Order processed\n";
        $content .= "[2026-05-08 10:00:02] INFO: User updated\n";
        File::put($this->logPath, $content);

        $result = $this->service->getLogData(search: 'User');

        expect($result['lines'])->toHaveCount(2);
    });

    it('filters by level', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        $content = "[2026-05-08 10:00:00] local.INFO: Normal\n";
        $content .= "[2026-05-08 10:00:01] local.ERROR: Bad\n";
        $content .= "[2026-05-08 10:00:02] local.INFO: Normal again\n";
        File::put($this->logPath, $content);

        $result = $this->service->getLogData(level: 'error');

        expect($result['lines'])->toHaveCount(1);
        expect($result['lines'][0]['level'])->toBe('error');
    });

    it('filters by warning level', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        $content = "[2026-05-08 10:00:00] local.INFO: Normal\n";
        $content .= "[2026-05-08 10:00:01] local.WARNING: Warning\n";
        $content .= "[2026-05-08 10:00:02] local.ERROR: Error\n";
        $content .= "[2026-05-08 10:00:03] local.WARN: Another warning\n";
        File::put($this->logPath, $content);

        $result = $this->service->getLogData(level: 'warning');

        expect($result['lines'])->toHaveCount(2);
    });

    it('filters by info level', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        $content = "[2026-05-08 10:00:00] local.INFO: Normal\n";
        $content .= "[2026-05-08 10:00:01] local.ERROR: Bad\n";
        File::put($this->logPath, $content);

        $result = $this->service->getLogData(level: 'info');

        expect($result['lines'])->toHaveCount(1);
        expect($result['lines'][0]['level'])->toBe('info');
    });

    it('combines search and level filters', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        $content = "[2026-05-08 10:00:00] local.ERROR: Database connection failed\n";
        $content .= "[2026-05-08 10:00:01] local.ERROR: Queue timeout\n";
        $content .= "[2026-05-08 10:00:02] local.INFO: Database query completed\n";
        File::put($this->logPath, $content);

        $result = $this->service->getLogData(search: 'Database', level: 'error');

        expect($result['lines'])->toHaveCount(1);
        expect($result['lines'][0]['text'])->toContain('connection failed');
    });

    it('returns empty lines when search finds nothing', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        $content = "[2026-05-08 10:00:00] INFO: Normal entry\n";
        File::put($this->logPath, $content);

        $result = $this->service->getLogData(search: 'NonExistent');

        expect($result['lines'])->toBe([]);
    });

    it('returns empty lines when level filter finds nothing', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        $content = "[2026-05-08 10:00:00] INFO: Normal entry\n";
        File::put($this->logPath, $content);

        $result = $this->service->getLogData(level: 'error');

        expect($result['lines'])->toBe([]);
    });

    it('handles log file with only newlines', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        File::put($this->logPath, "\n\n\n");

        $result = $this->service->getLogData();

        expect($result['stats']['lines'])->toBe(0);
        expect($result['lines'])->toBe([]);
    });

    it('handles very long log lines', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        $longLine = str_repeat('A', 10000);
        File::put($this->logPath, "[2026-05-08 10:00:00] INFO: {$longLine}\n");

        $result = $this->service->getLogData();

        expect($result['lines'])->toHaveCount(1);
        expect($result['lines'][0]['text'])->toContain('INFO');
    });

    it('handles large log files with many lines', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        $content = '';
        for ($i = 1; $i <= 500; $i++) {
            $content .= "[2026-05-08 10:00:00] INFO: Line {$i}\n";
        }
        File::put($this->logPath, $content);

        $result = $this->service->getLogData();

        expect($result['stats']['lines'])->toBe(500);
        expect($result['lines'])->toHaveCount(500);
    });

    it('handles log file with empty content', function () {
        /** @var TestCase&object{service:QueueWorkerLogService, logPath:string} $this */
        File::put($this->logPath, '');

        $result = $this->service->getLogData();

        expect($result['stats']['lines'])->toBe(0);
        expect($result['lines'])->toBe([]);
    });
});
