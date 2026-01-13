<?php

namespace LaraDBChat\Services;

use Illuminate\Support\Facades\DB;
use LaraDBChat\Exceptions\QueryExecutionException;

class QueryExecutor
{
    protected string $connection;
    protected array $config;

    public function __construct(?string $connection = null, array $config = [])
    {
        $this->connection = $connection ?? config('database.default');
        $this->config = array_merge([
            'enabled' => true,
            'read_only' => true,
            'max_results' => 100,
            'timeout' => 30,
        ], $config);
    }

    /**
     * Execute a SQL query and return results.
     */
    public function execute(string $sql): array
    {
        if (!$this->config['enabled']) {
            throw QueryExecutionException::executionFailed($sql, 'Query execution is disabled');
        }

        // Validate the query
        $this->validate($sql);

        // Clean the SQL
        $sql = $this->cleanSQL($sql);

        try {
            $startTime = microtime(true);

            // Add LIMIT if not present and we're doing a SELECT
            $sql = $this->addLimitIfNeeded($sql);

            // Execute the query
            $results = DB::connection($this->connection)->select($sql);

            $executionTime = microtime(true) - $startTime;

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results),
                'execution_time' => round($executionTime, 4),
                'sql' => $sql,
            ];
        } catch (\Exception $e) {
            throw QueryExecutionException::executionFailed($sql, $e->getMessage(), $e);
        }
    }

    /**
     * Validate a SQL query before execution.
     */
    public function validate(string $sql): void
    {
        $sql = trim($sql);

        // Check if empty
        if (empty($sql)) {
            throw QueryExecutionException::invalidSql($sql, 'SQL query is empty');
        }

        // In read-only mode, only allow SELECT queries
        if ($this->config['read_only']) {
            if (!$this->isSelectQuery($sql)) {
                throw QueryExecutionException::readOnlyViolation($sql);
            }
        }

        // Check for dangerous patterns
        $this->checkDangerousPatterns($sql);
    }

    /**
     * Check if the query is a SELECT query.
     */
    protected function isSelectQuery(string $sql): bool
    {
        $sql = strtoupper(trim($sql));

        // Allow SELECT and WITH (for CTEs)
        return str_starts_with($sql, 'SELECT') ||
               str_starts_with($sql, 'WITH');
    }

    /**
     * Check for dangerous SQL patterns.
     */
    protected function checkDangerousPatterns(string $sql): void
    {
        $dangerousPatterns = [
            '/;\s*(DROP|DELETE|TRUNCATE|ALTER|UPDATE|INSERT)/i' => 'Multiple statements with DDL/DML detected',
            '/INTO\s+OUTFILE/i' => 'INTO OUTFILE not allowed',
            '/INTO\s+DUMPFILE/i' => 'INTO DUMPFILE not allowed',
            '/LOAD_FILE/i' => 'LOAD_FILE not allowed',
            '/BENCHMARK\s*\(/i' => 'BENCHMARK not allowed',
            '/SLEEP\s*\(/i' => 'SLEEP not allowed',
            '/@@/i' => 'System variables access not allowed',
        ];

        foreach ($dangerousPatterns as $pattern => $message) {
            if (preg_match($pattern, $sql)) {
                throw QueryExecutionException::invalidSql($sql, $message);
            }
        }
    }

    /**
     * Clean and normalize SQL query.
     */
    protected function cleanSQL(string $sql): string
    {
        // Remove leading/trailing whitespace
        $sql = trim($sql);

        // Remove trailing semicolon (we'll add proper termination if needed)
        $sql = rtrim($sql, ';');

        // Remove markdown code blocks if present
        $sql = preg_replace('/^```sql\s*/i', '', $sql);
        $sql = preg_replace('/\s*```$/', '', $sql);

        return $sql;
    }

    /**
     * Add LIMIT clause if not present.
     */
    protected function addLimitIfNeeded(string $sql): string
    {
        if (!$this->isSelectQuery($sql)) {
            return $sql;
        }

        $upperSql = strtoupper($sql);

        // Check if LIMIT already exists
        if (str_contains($upperSql, ' LIMIT ')) {
            return $sql;
        }

        // Check if TOP exists (SQL Server)
        if (str_contains($upperSql, ' TOP ')) {
            return $sql;
        }

        // Add LIMIT based on driver
        $driver = DB::connection($this->connection)->getDriverName();

        return match ($driver) {
            'sqlsrv' => $this->addSQLServerLimit($sql),
            default => $sql . ' LIMIT ' . $this->config['max_results'],
        };
    }

    /**
     * Add SQL Server specific row limiting.
     */
    protected function addSQLServerLimit(string $sql): string
    {
        // SQL Server needs OFFSET-FETCH or TOP
        // If there's no ORDER BY, we need to add one
        $upperSql = strtoupper($sql);

        if (!str_contains($upperSql, ' ORDER BY ')) {
            $sql .= ' ORDER BY (SELECT NULL)';
        }

        return $sql . ' OFFSET 0 ROWS FETCH NEXT ' . $this->config['max_results'] . ' ROWS ONLY';
    }

    /**
     * Get query explanation (EXPLAIN output).
     */
    public function explain(string $sql): array
    {
        $sql = $this->cleanSQL($sql);
        $driver = DB::connection($this->connection)->getDriverName();

        try {
            $explainSql = match ($driver) {
                'mysql' => "EXPLAIN {$sql}",
                'pgsql' => "EXPLAIN (FORMAT JSON) {$sql}",
                'sqlite' => "EXPLAIN QUERY PLAN {$sql}",
                default => "EXPLAIN {$sql}",
            };

            return DB::connection($this->connection)->select($explainSql);
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Set the database connection.
     */
    public function setConnection(string $connection): self
    {
        $this->connection = $connection;
        return $this;
    }

    /**
     * Get configuration.
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
