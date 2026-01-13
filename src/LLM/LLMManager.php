<?php

namespace LaraDBChat\LLM;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

class LLMManager
{
    protected array $drivers = [];

    public function __construct(
        protected Container $container
    ) {}

    /**
     * Get the default LLM driver instance.
     */
    public function driver(?string $driver = null): LLMProviderInterface
    {
        $driver = $driver ?? $this->getDefaultDriver();

        return $this->drivers[$driver] ??= $this->resolve($driver);
    }

    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        return config('laradbchat.llm.provider', 'ollama');
    }

    /**
     * Resolve the given driver.
     */
    protected function resolve(string $driver): LLMProviderInterface
    {
        return match ($driver) {
            'ollama' => $this->container->make(OllamaProvider::class),
            'openai' => $this->container->make(OpenAIProvider::class),
            'claude' => $this->container->make(ClaudeProvider::class),
            default => throw new InvalidArgumentException("Unsupported LLM driver [{$driver}]"),
        };
    }

    /**
     * Get all available drivers.
     */
    public function getAvailableDrivers(): array
    {
        return ['ollama', 'openai', 'claude'];
    }

    /**
     * Dynamically call the default driver instance.
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->driver()->$method(...$parameters);
    }
}
