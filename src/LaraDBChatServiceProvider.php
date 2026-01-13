<?php

namespace LaraDBChat;

use Illuminate\Support\ServiceProvider;
use LaraDBChat\Console\Commands\AskCommand;
use LaraDBChat\Console\Commands\InstallCommand;
use LaraDBChat\Console\Commands\TrainCommand;
use LaraDBChat\LLM\ClaudeProvider;
use LaraDBChat\LLM\LLMManager;
use LaraDBChat\LLM\LLMProviderInterface;
use LaraDBChat\LLM\OllamaProvider;
use LaraDBChat\LLM\OpenAIProvider;
use LaraDBChat\Logging\DatabaseQueryLogger;
use LaraDBChat\Logging\FileQueryLogger;
use LaraDBChat\Logging\QueryLoggerInterface;
use LaraDBChat\Services\EmbeddingStore;
use LaraDBChat\Services\LaraDBChatService;
use LaraDBChat\Services\PromptBuilder;
use LaraDBChat\Services\QueryExecutor;
use LaraDBChat\Services\SchemaExtractor;

class LaraDBChatServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/laradbchat.php', 'laradbchat');

        // Register LLM providers
        $this->app->bind(OllamaProvider::class, function ($app) {
            $config = config('laradbchat.llm.ollama');
            return new OllamaProvider(
                $config['host'],
                $config['model'],
                $config['embedding_model'],
                $config['timeout'] ?? 120
            );
        });

        $this->app->bind(OpenAIProvider::class, function ($app) {
            $config = config('laradbchat.llm.openai');
            return new OpenAIProvider(
                $config['api_key'],
                $config['model'],
                $config['embedding_model'],
                $config['timeout'] ?? 60
            );
        });

        $this->app->bind(ClaudeProvider::class, function ($app) {
            $config = config('laradbchat.llm.claude');
            return new ClaudeProvider(
                $config['api_key'],
                $config['model'],
                $config['timeout'] ?? 60
            );
        });

        // Register LLM Manager
        $this->app->singleton(LLMManager::class, function ($app) {
            return new LLMManager($app);
        });

        // Bind the interface to the configured provider
        $this->app->bind(LLMProviderInterface::class, function ($app) {
            return $app->make(LLMManager::class)->driver();
        });

        // Register core services
        $this->app->singleton(SchemaExtractor::class, function ($app) {
            return new SchemaExtractor(
                config('laradbchat.connection'),
                config('laradbchat.training')
            );
        });

        $this->app->singleton(EmbeddingStore::class, function ($app) {
            return new EmbeddingStore(
                $app->make(LLMProviderInterface::class),
                config('laradbchat.connection'),
                config('laradbchat.embeddings')
            );
        });

        $this->app->singleton(PromptBuilder::class, function ($app) {
            return new PromptBuilder(
                config('laradbchat.training.sample_queries', [])
            );
        });

        $this->app->singleton(QueryExecutor::class, function ($app) {
            return new QueryExecutor(
                config('laradbchat.connection'),
                config('laradbchat.execution')
            );
        });

        // Register query logger based on configuration
        $this->app->singleton(QueryLoggerInterface::class, function ($app) {
            $config = config('laradbchat.logging');

            if (!$config['enabled']) {
                return new class implements QueryLoggerInterface {
                    public function log(string $question, string $sql, ?array $results = null, ?float $executionTime = null, ?string $error = null): void {}
                    public function getHistory(int $limit = 50, int $offset = 0): array { return []; }
                };
            }

            return match ($config['driver']) {
                'database' => new DatabaseQueryLogger(config('laradbchat.connection')),
                default => new FileQueryLogger($config['channel'], $config['path']),
            };
        });

        // Register main service
        $this->app->singleton(LaraDBChatService::class, function ($app) {
            return new LaraDBChatService(
                $app->make(LLMProviderInterface::class),
                $app->make(SchemaExtractor::class),
                $app->make(EmbeddingStore::class),
                $app->make(PromptBuilder::class),
                $app->make(QueryExecutor::class),
                $app->make(QueryLoggerInterface::class),
                config('laradbchat.execution')
            );
        });

        // Register facade alias
        $this->app->alias(LaraDBChatService::class, 'laradbchat');
    }

    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__ . '/../config/laradbchat.php' => config_path('laradbchat.php'),
        ], 'laradbchat-config');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'laradbchat-migrations');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Register routes
        if (config('laradbchat.api.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        }

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                TrainCommand::class,
                AskCommand::class,
            ]);
        }
    }
}
