<?php

namespace App\Services\General;

use App\Helpers\FormatBytes;
use Illuminate\Support\Collection;

class QueueWorkerLogService
{
    /**
     * Get the log file path.
     */
    public function getLogPath(): string
    {
        return storage_path('logs/queue-worker.log');
    }

    /**
     * Get parsed log data with optional filtering.
     *
     * @return array{stats: array{file: string, size: string, lines: int, modified: string}|null, lines: array<int, array{text: string, level: string}>}
     */
    public function getLogData(?string $search = null, ?string $level = null): array
    {
        $path = $this->getLogPath();

        if (! file_exists($path)) {
            return [
                'stats' => null,
                'lines' => [],
            ];
        }

        $contents = file_get_contents($path);

        $allLines = [];
        if ($contents !== false && trim($contents) !== '') {
            $allLines = explode("\n", rtrim($contents));
        }

        $stats = [
            'file' => 'logs/queue-worker.log',
            'size' => FormatBytes::formatBytes(filesize($path)),
            'lines' => count($allLines),
            'modified' => date('Y-m-d H:i:s', filemtime($path)),
        ];

        $lines = $this->parseLines($allLines);

        if ($search) {
            $lines = $this->filterBySearch($lines, $search);
        }

        if ($level) {
            $lines = $this->filterByLevel($lines, $level);
        }

        return [
            'stats' => $stats,
            'lines' => $lines->values()->toArray(),
        ];
    }

    /**
     * Parse raw log lines into structured data with detected levels.
     *
     * @param  array<int, string>  $rawLines
     * @return Collection<int, array{text: string, level: string}>
     */
    public function parseLines(array $rawLines): Collection
    {
        return collect($rawLines)->map(function (string $line) {
            return [
                'text' => $line,
                'level' => $this->detectLevel($line),
            ];
        });
    }

    /**
     * Filter lines by search query (case-insensitive).
     *
     * @param  Collection<int, array{text: string, level: string}>  $lines
     * @return Collection<int, array{text: string, level: string}>
     */
    public function filterBySearch(Collection $lines, string $search): Collection
    {
        return $lines->filter(fn (array $line) => str_contains(
            strtolower($line['text']),
            strtolower($search)
        ))->values();
    }

    /**
     * Filter lines by log level.
     *
     * @param  Collection<int, array{text: string, level: string}>  $lines
     * @return Collection<int, array{text: string, level: string}>
     */
    public function filterByLevel(Collection $lines, string $level): Collection
    {
        return $lines->filter(fn (array $line) => $line['level'] === $level)->values();
    }

    /**
     * Detect log level from a line of text.
     */
    public function detectLevel(string $line): string
    {
        if (preg_match('/\.(ERROR|CRITICAL|ALERT|EMERGENCY):/', $line)) {
            return 'error';
        }

        if (preg_match('/\.(WARNING|WARN):/', $line)) {
            return 'warning';
        }

        return 'info';
    }
}
