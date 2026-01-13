<?php

namespace LaraDBChat\Logging;

use Illuminate\Support\Facades\DB;

class DatabaseQueryLogger implements QueryLoggerInterface
{
    protected string $table = 'laradbchat_query_logs';
    protected ?string $connection;

    public function __construct(?string $connection = null)
    {
        $this->connection = $connection;
    }

    public function log(
        string $question,
        string $sql,
        ?array $results = null,
        ?float $executionTime = null,
        ?string $error = null
    ): void {
        $this->getConnection()->table($this->table)->insert([
            'question' => $question,
            'generated_sql' => $sql,
            'results' => $results !== null ? json_encode(array_slice($results, 0, 100)) : null,
            'result_count' => $results !== null ? count($results) : null,
            'execution_time' => $executionTime,
            'error' => $error,
            'status' => $error ? 'error' : 'success',
            'llm_provider' => config('laradbchat.llm.provider'),
            'metadata' => json_encode([
                'ip' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function getHistory(int $limit = 50, int $offset = 0): array
    {
        return $this->getConnection()
            ->table($this->table)
            ->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'question' => $row->question,
                    'sql' => $row->generated_sql,
                    'result_count' => $row->result_count,
                    'execution_time' => $row->execution_time,
                    'status' => $row->status,
                    'error' => $row->error,
                    'provider' => $row->llm_provider,
                    'timestamp' => $row->created_at,
                ];
            })
            ->toArray();
    }

    /**
     * Get statistics about queries.
     */
    public function getStats(): array
    {
        $connection = $this->getConnection();

        return [
            'total_queries' => $connection->table($this->table)->count(),
            'successful_queries' => $connection->table($this->table)->where('status', 'success')->count(),
            'failed_queries' => $connection->table($this->table)->where('status', 'error')->count(),
            'avg_execution_time' => $connection->table($this->table)->whereNotNull('execution_time')->avg('execution_time'),
            'queries_today' => $connection->table($this->table)->whereDate('created_at', today())->count(),
        ];
    }

    /**
     * Clear old logs.
     */
    public function clearOlderThan(int $days = 30): int
    {
        return $this->getConnection()
            ->table($this->table)
            ->where('created_at', '<', now()->subDays($days))
            ->delete();
    }

    /**
     * Get the database connection.
     */
    protected function getConnection()
    {
        return DB::connection($this->connection);
    }
}
