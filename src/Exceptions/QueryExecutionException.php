<?php

namespace LaraDBChat\Exceptions;

use Exception;

class QueryExecutionException extends Exception
{
    public function __construct(
        string $message = 'Failed to execute query',
        int $code = 0,
        ?Exception $previous = null,
        public readonly ?string $sql = null,
        public readonly ?array $context = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function invalidSql(string $sql, string $reason, ?Exception $previous = null): self
    {
        return new self(
            "Invalid SQL query: {$reason}",
            400,
            $previous,
            $sql,
            ['reason' => $reason]
        );
    }

    public static function readOnlyViolation(string $sql): self
    {
        return new self(
            'Only SELECT queries are allowed in read-only mode',
            403,
            null,
            $sql,
            ['violation' => 'read_only']
        );
    }

    public static function executionFailed(string $sql, string $error, ?Exception $previous = null): self
    {
        return new self(
            "Query execution failed: {$error}",
            500,
            $previous,
            $sql,
            ['error' => $error]
        );
    }

    public static function timeout(string $sql, int $timeout): self
    {
        return new self(
            "Query execution timed out after {$timeout} seconds",
            408,
            null,
            $sql,
            ['timeout' => $timeout]
        );
    }
}
