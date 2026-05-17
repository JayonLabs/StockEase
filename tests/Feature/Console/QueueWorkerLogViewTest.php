<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->logPath = storage_path('logs/queue-worker.log');

    if (File::exists($this->logPath)) {
        File::delete($this->logPath);
    }
});

afterEach(function () {
    if (File::exists($this->logPath)) {
        File::delete($this->logPath);
    }
});

it('shows a message when the log file does not exist', function () {
    $this->artisan('logs:queue-worker')
        ->expectsOutputToContain('No queue worker log file found')
        ->assertSuccessful();
});

it('shows an info message when the log file is empty', function () {
    File::put($this->logPath, '');

    $this->artisan('logs:queue-worker')
        ->expectsOutputToContain('log file is empty')
        ->assertSuccessful();
});

it('displays the full log content when no options are given', function () {
    $content = "[2026-05-08 10:00:00] INFO: Processing job\n[2026-05-08 10:00:01] INFO: Job completed\n";
    File::put($this->logPath, $content);

    $this->artisan('logs:queue-worker')
        ->expectsOutputToContain('Processing job')
        ->expectsOutputToContain('Job completed')
        ->assertSuccessful();
});

it('displays file statistics in the header', function () {
    $content = "[2026-05-08 10:00:00] INFO: Test entry\n";
    File::put($this->logPath, $content);

    $this->artisan('logs:queue-worker')
        ->expectsOutputToContain('Queue Worker Log')
        ->expectsOutputToContain('File')
        ->expectsOutputToContain('Size')
        ->expectsOutputToContain('Lines')
        ->expectsOutputToContain('Modified')
        ->assertSuccessful();
});

it('shows only the last N lines when --lines option is given', function () {
    $lines = [];
    for ($i = 1; $i <= 10; $i++) {
        $lines[] = "[2026-05-08 10:00:0{$i}] INFO: Line {$i}";
    }
    File::put($this->logPath, implode("\n", $lines));

    $this->artisan('logs:queue-worker', ['--lines' => 3])
        ->expectsOutputToContain('Showing 3 of 10')
        ->expectsOutputToContain('Line 8')
        ->expectsOutputToContain('Line 9')
        ->expectsOutputToContain('Line 10')
        ->doesntExpectOutputToContain('Line 1')
        ->assertSuccessful();
});

it('filters lines containing given text with --filter option', function () {
    $content = "[2026-05-08 10:00:00] INFO: User created\n[2026-05-08 10:00:01] INFO: Order processed\n[2026-05-08 10:00:02] INFO: User updated\n";
    File::put($this->logPath, $content);

    $this->artisan('logs:queue-worker', ['--filter' => 'User'])
        ->expectsOutputToContain('User created')
        ->expectsOutputToContain('User updated')
        ->doesntExpectOutputToContain('Order processed')
        ->assertSuccessful();
});

it('shows only error and warning lines with --errors option', function () {
    $content = "[2026-05-08 10:00:00] INFO: Normal entry\n[2026-05-08 10:00:01] WARNING: Low memory\n[2026-05-08 10:00:02] ERROR: Job failed\n[2026-05-08 10:00:03] INFO: Another entry\n[2026-05-08 10:00:04] exception: Database down\n";
    File::put($this->logPath, $content);

    $this->artisan('logs:queue-worker', ['--errors' => true])
        ->expectsOutputToContain('Low memory')
        ->expectsOutputToContain('Job failed')
        ->expectsOutputToContain('Database down')
        ->doesntExpectOutputToContain('Normal entry')
        ->doesntExpectOutputToContain('Another entry')
        ->assertSuccessful();
});

it('shows no matching entries message when filter returns nothing', function () {
    $content = "[2026-05-08 10:00:00] INFO: Normal entry\n";
    File::put($this->logPath, $content);

    $this->artisan('logs:queue-worker', ['--filter' => 'NonExistentText'])
        ->expectsOutputToContain('No matching log entries found')
        ->assertSuccessful();
});

it('shows no matching entries when --errors flag finds no errors', function () {
    $content = "[2026-05-08 10:00:00] INFO: Normal entry\n[2026-05-08 10:00:01] INFO: All good\n";
    File::put($this->logPath, $content);

    $this->artisan('logs:queue-worker', ['--errors' => true])
        ->expectsOutputToContain('No matching log entries found')
        ->assertSuccessful();
});

it('shows entry count when displaying filtered results', function () {
    $content = "[2026-05-08 10:00:00] INFO: Entry A\n[2026-05-08 10:00:01] ERROR: Entry B\n[2026-05-08 10:00:02] INFO: Entry C\n";
    File::put($this->logPath, $content);

    $this->artisan('logs:queue-worker', ['--errors' => true])
        ->expectsOutputToContain('Showing 1 of 3')
        ->assertSuccessful();
});

it('displays error lines with error styling', function () {
    $content = "[2026-05-08 10:00:00] ERROR: Something went wrong\n[2026-05-08 10:00:01] WARNING: Approaching limit\n";
    File::put($this->logPath, $content);

    $this->artisan('logs:queue-worker')
        ->expectsOutputToContain('Something went wrong')
        ->expectsOutputToContain('Approaching limit')
        ->assertSuccessful();
});

it('combines --lines and --filter options together', function () {
    $lines = [];
    for ($i = 1; $i <= 10; $i++) {
        $lines[] = "[2026-05-08 10:00:0{$i}] INFO: Entry {$i}";
    }
    $lines[4] = '[2026-05-08 10:00:05] INFO: Match this one';
    $lines[8] = '[2026-05-08 10:00:09] INFO: Match here too';
    File::put($this->logPath, implode("\n", $lines));

    $this->artisan('logs:queue-worker', ['--lines' => 1, '--filter' => 'Match'])
        ->expectsOutputToContain('Match here too')
        ->doesntExpectOutputToContain('Match this one')
        ->assertSuccessful();
});

it('combines --lines and --errors options together', function () {
    $content = "[2026-05-08 10:00:01] INFO: Normal 1\n";
    $content .= "[2026-05-08 10:00:02] ERROR: Error 1\n";
    $content .= "[2026-05-08 10:00:03] INFO: Normal 2\n";
    $content .= "[2026-05-08 10:00:04] ERROR: Error 2\n";
    $content .= "[2026-05-08 10:00:05] WARNING: Warning 1\n";
    File::put($this->logPath, $content);

    $this->artisan('logs:queue-worker', ['--errors' => true, '--lines' => 2])
        ->expectsOutputToContain('Showing 2')
        ->expectsOutputToContain('Error 2')
        ->expectsOutputToContain('Warning 1')
        ->doesntExpectOutputToContain('Error 1')
        ->assertSuccessful();
});

it('handles large log files efficiently', function () {
    $lines = [];
    for ($i = 1; $i <= 500; $i++) {
        $lines[] = "[2026-05-08 10:00:00] INFO: Processing item {$i}";
    }
    File::put($this->logPath, implode("\n", $lines));

    $this->artisan('logs:queue-worker')
        ->expectsOutputToContain('Lines')
        ->expectsOutputToContain('Showing 500 of 500')
        ->assertSuccessful();
});

it('formats file size in human readable format', function () {
    $content = str_repeat('x', 1536);
    File::put($this->logPath, $content);

    $this->artisan('logs:queue-worker')
        ->expectsOutputToContain('1.5 KB')
        ->assertSuccessful();
});

it('handles log lines with special characters', function () {
    $content = "[2026-05-08 10:00:00] INFO: User 'john@example.com' processed\n";
    $content .= "[2026-05-08 10:00:01] INFO: Price: $100.50 & discount applied\n";
    File::put($this->logPath, $content);

    $this->artisan('logs:queue-worker')
        ->expectsOutputToContain('john@example.com')
        ->expectsOutputToContain('$100.50')
        ->assertSuccessful();
});
