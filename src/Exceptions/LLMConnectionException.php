<?php

namespace LaraDBChat\Exceptions;

use Exception;

class LLMConnectionException extends Exception
{
    public function __construct(
        string $message = 'Failed to connect to LLM provider',
        int $code = 0,
        ?Exception $previous = null,
        public readonly ?string $provider = null,
        public readonly ?array $context = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function connectionFailed(string $provider, string $host, ?Exception $previous = null): self
    {
        return new self(
            "Failed to connect to {$provider} at {$host}",
            0,
            $previous,
            $provider,
            ['host' => $host]
        );
    }

    public static function authenticationFailed(string $provider, ?Exception $previous = null): self
    {
        return new self(
            "Authentication failed for {$provider}. Please check your API key.",
            401,
            $previous,
            $provider
        );
    }

    public static function rateLimited(string $provider, ?Exception $previous = null): self
    {
        return new self(
            "Rate limit exceeded for {$provider}. Please try again later.",
            429,
            $previous,
            $provider
        );
    }

    public static function timeout(string $provider, int $timeout, ?Exception $previous = null): self
    {
        return new self(
            "Request to {$provider} timed out after {$timeout} seconds",
            408,
            $previous,
            $provider,
            ['timeout' => $timeout]
        );
    }
}
