<?php

namespace LaraDBChat\Logging;

use Illuminate\Support\Facades\Log;

class FileQueryLogger implements QueryLoggerInterface
{
    protected string $channel;
    protected string $path;
    protected array $logs = [];

    public function __construct(string $channel = 'laradbchat', ?string $path = null)
    {
        $this->channel = $channel;
        $this->path = $path ?? storage_path('logs/laradbchat.log');

        // Configure the channel dynamically if it doesn't exist
        $this->ensureChannelExists();
    }

    /**
     * Ensure the log channel exists.
     */
    protected function ensureChannelExists(): void
    {
        $channels = config('logging.channels', []);

        if (!isset($channels[$this->channel])) {
            config(["logging.channels.{$this->channel}" => [
                'driver' => 'single',
                'path' => $this->path,
                'level' => 'debug',
            ]]);
        }
    }

    public function log(
        string $question,
        string $sql,
        ?array $results = null,
        ?float $executionTime = null,
        ?string $error = null
    ): void {
        $logData = [
            'timestamp' => now()->toIso8601String(),
            'question' => $question,
            'sql' => $sql,
            'result_count' => $results !== null ? count($results) : null,
            'execution_time' => $executionTime,
            'status' => $error ? 'error' : 'success',
            'error' => $error,
        ];

        // Keep in memory for getHistory
        array_unshift($this->logs, $logData);

        // Write to log file
        if ($error) {
            Log::channel($this->channel)->error('LaraDBChat Query Failed', $logData);
        } else {
            Log::channel($this->channel)->info('LaraDBChat Query', $logData);
        }
    }

    public function getHistory(int $limit = 50, int $offset = 0): array
    {
        // Try to read from log file
        $history = $this->readFromFile();

        // Merge with in-memory logs
        $history = array_merge($this->logs, $history);

        // Remove duplicates based on timestamp
        $history = $this->uniqueByTimestamp($history);

        // Sort by timestamp descending
        usort($history, fn($a, $b) => strcmp($b['timestamp'] ?? '', $a['timestamp'] ?? ''));

        return array_slice($history, $offset, $limit);
    }

    /**
     * Read logs from file.
     */
    protected function readFromFile(): array
    {
        if (!file_exists($this->path)) {
            return [];
        }

        $logs = [];
        $lines = file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach (array_reverse($lines) as $line) {
            // Try to parse JSON from log line
            if (preg_match('/\{.*\}/', $line, $matches)) {
                $data = json_decode($matches[0], true);
                if (is_array($data) && isset($data['question'])) {
                    $logs[] = $data;
                }
            }

            // Limit to last 1000 entries
            if (count($logs) >= 1000) {
                break;
            }
        }

        return $logs;
    }

    /**
     * Remove duplicate entries based on timestamp.
     */
    protected function uniqueByTimestamp(array $logs): array
    {
        $seen = [];
        $unique = [];

        foreach ($logs as $log) {
            $key = $log['timestamp'] ?? uniqid();
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $log;
            }
        }

        return $unique;
    }
}
