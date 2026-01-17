<?php

namespace LaraDBChat\Console\Commands;

use Illuminate\Console\Command;
use LaraDBChat\Database\MigrationRunner;

class MigrateCommand extends Command
{
    protected $signature = 'laradbchat:migrate
                            {--connection= : Override the storage connection}
                            {--fresh : Drop existing tables first}
                            {--status : Show migration status without running}';

    protected $description = 'Run LaraDBChat migrations on the configured storage connection';

    public function handle(): int
    {
        $connection = $this->option('connection')
            ?? config('laradbchat.storage.connection')
            ?? config('laradbchat.connection');

        $connectionDisplay = $connection ?? 'default';

        // Status check only
        if ($this->option('status')) {
            return $this->showStatus($connection, $connectionDisplay);
        }

        $this->info("LaraDBChat Migration");
        $this->line("Connection: {$connectionDisplay}");
        $this->newLine();

        // Ensure SQLite database exists if using laradbchat_sqlite
        if ($connection === 'laradbchat_sqlite') {
            $this->info('Setting up SQLite database...');
            MigrationRunner::ensureSqliteDatabase();
            $sqlitePath = config('laradbchat.storage.sqlite_path', storage_path('laradbchat/database.sqlite'));
            $this->line("SQLite path: {$sqlitePath}");
            $this->newLine();
        }

        // Fresh migration (drop tables first)
        if ($this->option('fresh')) {
            if (!$this->confirm('This will drop all LaraDBChat tables and data. Continue?', false)) {
                $this->info('Migration cancelled.');
                return self::SUCCESS;
            }

            $this->warn('Dropping existing LaraDBChat tables...');
            MigrationRunner::rollback($connection);
            $this->info('Tables dropped.');
            $this->newLine();
        }

        // Check if tables already exist
        if (MigrationRunner::tablesExist($connection)) {
            $this->info('LaraDBChat tables already exist.');
            $this->line('Use --fresh to recreate tables (this will delete all data).');
            return self::SUCCESS;
        }

        // Run migrations
        $this->info('Running LaraDBChat migrations...');

        try {
            MigrationRunner::run($connection);
            $this->info('Migrations completed successfully.');
            $this->newLine();

            $this->table(
                ['Table', 'Status'],
                [
                    ['laradbchat_embeddings', '<fg=green>Created</>'],
                    ['laradbchat_query_logs', '<fg=green>Created</>'],
                ]
            );

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Migration failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    protected function showStatus(?string $connection, string $connectionDisplay): int
    {
        $this->info("LaraDBChat Migration Status");
        $this->line("Connection: {$connectionDisplay}");
        $this->newLine();

        $embeddingsExists = \Illuminate\Support\Facades\Schema::connection($connection)
            ->hasTable('laradbchat_embeddings');
        $queryLogsExists = \Illuminate\Support\Facades\Schema::connection($connection)
            ->hasTable('laradbchat_query_logs');

        $this->table(
            ['Table', 'Status'],
            [
                ['laradbchat_embeddings', $embeddingsExists ? '<fg=green>Exists</>' : '<fg=yellow>Missing</>'],
                ['laradbchat_query_logs', $queryLogsExists ? '<fg=green>Exists</>' : '<fg=yellow>Missing</>'],
            ]
        );

        if ($embeddingsExists && $queryLogsExists) {
            $this->info('All LaraDBChat tables are present.');
        } else {
            $this->warn('Some tables are missing. Run: php artisan laradbchat:migrate');
        }

        return self::SUCCESS;
    }
}
