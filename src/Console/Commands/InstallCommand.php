<?php

namespace LaraDBChat\Console\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'laradbchat:install
                            {--force : Overwrite existing configuration}
                            {--skip-training : Skip the training step}';

    protected $description = 'Install the LaraDBChat package';

    public function handle(): int
    {
        $this->info('Installing LaraDBChat...');
        $this->newLine();

        // Publish configuration
        $this->publishConfig();

        // Run migrations
        $this->runMigrations();

        // Configure LLM provider
        $this->configureLLMProvider();

        // Offer to run training
        if (!$this->option('skip-training')) {
            $this->offerTraining();
        }

        // Show final summary
        $this->showFinalSummary();

        return self::SUCCESS;
    }

    protected function publishConfig(): void
    {
        $this->info('Publishing configuration...');

        $params = ['--provider' => 'LaraDBChat\LaraDBChatServiceProvider'];

        if ($this->option('force')) {
            $params['--force'] = true;
        }

        $this->call('vendor:publish', array_merge($params, ['--tag' => 'laradbchat-config']));
    }

    protected function runMigrations(): void
    {
        if ($this->confirm('Would you like to run the migrations now?', true)) {
            $this->info('Running migrations...');
            $this->call('migrate');
        } else {
            $this->warn('Remember to run migrations later: php artisan migrate');
        }
    }

    protected function configureLLMProvider(): void
    {
        $this->newLine();
        $this->info('LLM Provider Configuration');
        $this->line('LaraDBChat supports multiple LLM providers.');
        $this->newLine();

        $provider = $this->choice(
            'Which LLM provider would you like to use?',
            [
                'ollama' => 'Ollama (Local, Free - requires Ollama installed)',
                'openai' => 'OpenAI (API key required)',
                'claude' => 'Claude/Anthropic (API key required)',
            ],
            'ollama'
        );

        $envPath = base_path('.env');
        $envContent = file_exists($envPath) ? file_get_contents($envPath) : '';

        // Add or update LARADBCHAT_LLM_PROVIDER
        $envContent = $this->updateEnvValue($envContent, 'LARADBCHAT_LLM_PROVIDER', $provider);

        switch ($provider) {
            case 'ollama':
                $this->configureOllama($envContent, $envPath);
                break;
            case 'openai':
                $this->configureOpenAI($envContent, $envPath);
                break;
            case 'claude':
                $this->configureClaude($envContent, $envPath);
                break;
        }

        $this->info("LLM provider set to: {$provider}");
    }

    protected function configureOllama(string $envContent, string $envPath): void
    {
        $host = $this->ask('Ollama host URL', 'http://localhost:11434');
        $model = $this->ask('Ollama model for SQL generation', 'qwen2.5-coder:3b');
        $embeddingModel = $this->ask('Ollama embedding model', 'nomic-embed-text');

        $envContent = $this->updateEnvValue($envContent, 'OLLAMA_HOST', $host);
        $envContent = $this->updateEnvValue($envContent, 'OLLAMA_MODEL', $model);
        $envContent = $this->updateEnvValue($envContent, 'OLLAMA_EMBEDDING_MODEL', $embeddingModel);

        file_put_contents($envPath, $envContent);

        $this->newLine();
        $this->warn('Make sure you have Ollama running and the models pulled:');
        $this->line("  ollama pull {$model}");
        $this->line("  ollama pull {$embeddingModel}");
    }

    protected function configureOpenAI(string $envContent, string $envPath): void
    {
        $apiKey = $this->secret('Enter your OpenAI API key');

        if ($apiKey) {
            $envContent = $this->updateEnvValue($envContent, 'OPENAI_API_KEY', $apiKey);
            file_put_contents($envPath, $envContent);
        } else {
            $this->warn('API key not provided. Add OPENAI_API_KEY to your .env file.');
        }
    }

    protected function configureClaude(string $envContent, string $envPath): void
    {
        $apiKey = $this->secret('Enter your Anthropic API key');

        if ($apiKey) {
            $envContent = $this->updateEnvValue($envContent, 'ANTHROPIC_API_KEY', $apiKey);
            file_put_contents($envPath, $envContent);
        } else {
            $this->warn('API key not provided. Add ANTHROPIC_API_KEY to your .env file.');
        }
    }

    protected function updateEnvValue(string $envContent, string $key, string $value): string
    {
        $pattern = "/^{$key}=.*/m";

        if (preg_match($pattern, $envContent)) {
            return preg_replace($pattern, "{$key}={$value}", $envContent);
        }

        return $envContent . "\n{$key}={$value}";
    }

    protected function offerTraining(): void
    {
        $this->newLine();
        $this->info('Database Training');
        $this->line('Training helps LaraDBChat understand your database structure.');
        $this->newLine();

        if (!$this->confirm('Would you like to train LaraDBChat on your database now?', true)) {
            $this->warn('You can train later with: php artisan laradbchat:train');
            return;
        }

        // Ask about deep analysis
        $deepAnalysis = $this->confirm(
            'Analyze Laravel Models and Migrations for better accuracy? (Recommended)',
            true
        );

        // Run training (skip doc prompt since we handle it ourselves)
        $options = ['--no-docs-prompt' => true];
        if ($deepAnalysis) {
            $options['--deep'] = true;
        }

        $this->call('laradbchat:train', $options);

        // Offer to add documentation
        $this->newLine();
        $this->info('Custom Documentation');
        $this->line('Adding business-specific documentation improves query accuracy.');
        $this->newLine();

        if ($this->confirm('Would you like to add custom documentation now?', false)) {
            $this->addDocumentationWizard();
        }

        // Offer JSON file import
        if ($this->confirm('Do you have a training JSON file to import?', false)) {
            $file = $this->ask('Enter the path to your JSON file');
            if ($file && file_exists($file)) {
                $this->call('laradbchat:add-docs', ['--file' => $file]);
            } else {
                $this->warn('File not found. You can import later with: php artisan laradbchat:add-docs --file=path/to/file.json');
            }
        }
    }

    protected function addDocumentationWizard(): void
    {
        $this->newLine();
        $this->info('Documentation Wizard');
        $this->line('Describe your database tables and business logic.');
        $this->line('Examples:');
        $this->line('  - "The orders table uses status values: pending, confirmed, shipped, delivered"');
        $this->line('  - "User types are determined by the role_id column linking to roles table"');
        $this->newLine();

        while (true) {
            $title = $this->ask('Documentation title (or "done" to finish)');

            if (empty($title) || strtolower($title) === 'done') {
                break;
            }

            $content = $this->ask('Description/content');

            if (empty($content)) {
                $this->warn('Content is required. Skipping.');
                continue;
            }

            // Add documentation
            $service = app(\LaraDBChat\Services\LaraDBChatService::class);
            $service->addDocumentation($title, $content);

            $this->info("Added: {$title}");
            $this->newLine();
        }
    }

    protected function showFinalSummary(): void
    {
        $this->newLine();
        $this->info('LaraDBChat installed successfully!');
        $this->newLine();

        $this->line('Quick reference:');
        $this->line('  php artisan laradbchat:ask "Your question"  - Ask a question');
        $this->line('  php artisan laradbchat:ask --interactive    - Interactive mode');
        $this->line('  php artisan laradbchat:train --fresh        - Retrain from scratch');
        $this->line('  php artisan laradbchat:add-docs             - Add documentation');
        $this->newLine();

        if (config('laradbchat.api.enabled', true)) {
            $this->line('API endpoints available at:');
            $this->line('  POST /api/laradbchat/ask     - Ask questions via API');
            $this->line('  GET  /api/laradbchat/status  - Check training status');
        }
    }
}
