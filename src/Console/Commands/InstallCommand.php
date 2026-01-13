<?php

namespace LaraDBChat\Console\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'laradbchat:install
                            {--force : Overwrite existing configuration}';

    protected $description = 'Install the LaraDBChat package';

    public function handle(): int
    {
        $this->info('Installing LaraDBChat...');

        // Publish configuration
        $this->publishConfig();

        // Run migrations
        $this->runMigrations();

        // Show next steps
        $this->showNextSteps();

        $this->newLine();
        $this->info('LaraDBChat installed successfully!');

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

    protected function showNextSteps(): void
    {
        $this->newLine();
        $this->info('Next steps:');
        $this->newLine();

        $this->line('1. Configure your LLM provider in .env:');
        $this->newLine();
        $this->line('   # For Ollama (local, free):');
        $this->line('   LARADBCHAT_LLM_PROVIDER=ollama');
        $this->line('   OLLAMA_HOST=http://localhost:11434');
        $this->line('   OLLAMA_MODEL=qwen2.5-coder:3b');
        $this->newLine();
        $this->line('   # For OpenAI:');
        $this->line('   LARADBCHAT_LLM_PROVIDER=openai');
        $this->line('   OPENAI_API_KEY=your-api-key');
        $this->newLine();
        $this->line('   # For Claude:');
        $this->line('   LARADBCHAT_LLM_PROVIDER=claude');
        $this->line('   ANTHROPIC_API_KEY=your-api-key');
        $this->newLine();

        $this->line('2. Train LaraDBChat on your database schema:');
        $this->line('   php artisan laradbchat:train');
        $this->newLine();

        $this->line('3. Start asking questions:');
        $this->line('   php artisan laradbchat:ask "Show all users"');
    }
}
