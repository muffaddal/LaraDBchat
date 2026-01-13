<?php

namespace LaraDBChat\Logging;

interface QueryLoggerInterface
{
    /**
     * Log a query and its results.
     */
    public function log(
        string $question,
        string $sql,
        ?array $results = null,
        ?float $executionTime = null,
        ?string $error = null
    ): void;

    /**
     * Get query history.
     */
    public function getHistory(int $limit = 50, int $offset = 0): array;
}
