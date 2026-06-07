<?php

namespace App\Console\Commands\Logs;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('logs:queue-worker
            {--lines= : Show only the last N lines}
            {--filter= : Filter lines containing the given text}
            {--errors : Show only error/warning lines}')]
#[Description('View the queue worker log file output')]
class QueueWorkerLogView extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $path = storage_path('logs/queue-worker.log');

        if (! file_exists($path)) {
            $this->warn('No queue worker log file found.');
            $this->line('Expected at: '.$path);

            return static::SUCCESS;
        }

        $contents = file_get_contents($path);

        if ($contents === false || $contents === '') {
            $this->info('The queue worker log file is empty.');

            return static::SUCCESS;
        }

        $lines = explode("\n", rtrim($contents));
        $totalLines = count($lines);

        $this->info('Queue Worker Log');
        $this->info('================');
        $this->line('  File     : '.$path);
        $this->line('  Size     : '.$this->formatBytes(filesize($path)));
        $this->line('  Lines    : '.$totalLines);
        $this->line('  Modified : '.date('Y-m-d H:i:s', filemtime($path)));
        $this->newLine();

        if ($this->option('errors')) {
            $lines = array_values(array_filter($lines, function (string $line) {
                return stripos($line, 'error') !== false
                    || stripos($line, 'warning') !== false
                    || stripos($line, 'exception') !== false
                    || stripos($line, 'failed') !== false;
            }));
        }

        if ($filter = $this->option('filter')) {
            $lines = array_values(array_filter($lines, function (string $line) use ($filter) {
                return str_contains($line, $filter);
            }));
        }

        if ($linesLimit = $this->option('lines')) {
            $linesLimit = (int) $linesLimit;
            if ($linesLimit > 0 && $linesLimit < count($lines)) {
                $lines = array_slice($lines, -$linesLimit);
            }
        }

        if (empty($lines)) {
            $this->info('No matching log entries found.');

            return static::SUCCESS;
        }

        $this->line('  Showing '.count($lines).' of '.$totalLines.' entries.');
        $this->newLine();

        foreach ($lines as $line) {
            if (stripos($line, 'error') !== false || stripos($line, 'exception') !== false) {
                $this->error($line);
            } elseif (stripos($line, 'warning') !== false) {
                $this->warn($line);
            } else {
                $this->line($line);
            }
        }

        return static::SUCCESS;
    }

    /**
     * Format the given bytes to human-readable format.
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision).' '.$units[$pow];
    }
}
