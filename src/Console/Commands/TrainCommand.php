<?php

namespace LaraDBChat\Console\Commands;

use Illuminate\Console\Command;
use LaraDBChat\Services\LaraDBChatService;

class TrainCommand extends Command
{
    protected $signature = 'laradbchat:train
                            {--fresh : Clear existing training data before training}
                            {--show-schema : Display the extracted schema}';

    protected $description = 'Train LaraDBChat on your database schema';

    public function handle(LaraDBChatService $service): int
    {
        $this->info('Training LaraDBChat on your database schema...');
        $this->newLine();

        // Clear existing training data if --fresh
        if ($this->option('fresh')) {
            $this->warn('Clearing existing training data...');
            $cleared = $service->clearTraining();
            $this->info("Cleared {$cleared} embeddings.");
            $this->newLine();
        }

        // Show schema if requested
        if ($this->option('show-schema')) {
            $this->showSchema($service);
        }

        // Train
        $this->info('Extracting schema and generating embeddings...');
        $this->info('This may take a few minutes depending on your database size.');
        $this->newLine();

        $progressBar = $this->output->createProgressBar();
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% -- %message%');

        try {
            $schema = $service->getSchema();
            $progressBar->setMaxSteps(count($schema));

            $result = $service->train();

            $progressBar->finish();
            $this->newLine(2);

            if ($result['success']) {
                $this->info("Training completed successfully!");
                $this->table(
                    ['Metric', 'Value'],
                    [
                        ['Tables Trained', $result['tables_trained']],
                        ['Total Tables', $result['total_tables']],
                        ['Success Rate', round(($result['tables_trained'] / max($result['total_tables'], 1)) * 100, 1) . '%'],
                    ]
                );
            } else {
                $this->warn("Training completed with some errors.");
                $this->table(
                    ['Metric', 'Value'],
                    [
                        ['Tables Trained', $result['tables_trained']],
                        ['Total Tables', $result['total_tables']],
                        ['Errors', count($result['errors'])],
                    ]
                );

                if (!empty($result['errors'])) {
                    $this->newLine();
                    $this->error('Errors:');
                    foreach ($result['errors'] as $table => $error) {
                        $this->line("  - {$table}: {$error}");
                    }
                }
            }

            // Show training status
            $this->newLine();
            $this->showTrainingStatus($service);

            return $result['success'] ? self::SUCCESS : self::FAILURE;

        } catch (\Exception $e) {
            $progressBar->finish();
            $this->newLine(2);
            $this->error("Training failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    protected function showSchema(LaraDBChatService $service): void
    {
        $this->info('Database Schema:');
        $this->newLine();

        $schema = $service->getSchema();

        foreach ($schema as $tableName => $tableInfo) {
            $this->line("Table: {$tableName}");
            $columns = array_map(function ($col) {
                return [$col['name'], $col['type'], $col['nullable'] ? 'YES' : 'NO'];
            }, $tableInfo['columns']);

            $this->table(['Column', 'Type', 'Nullable'], $columns);
            $this->newLine();
        }
    }

    protected function showTrainingStatus(LaraDBChatService $service): void
    {
        $status = $service->getTrainingStatus();

        $this->info('Training Status:');
        $this->table(
            ['Type', 'Count'],
            [
                ['Table Schemas', $status['tables']],
                ['Descriptions', $status['descriptions']],
                ['Sample Queries', $status['samples']],
                ['Documentation', $status['documentation']],
                ['Total Embeddings', $status['total']],
            ]
        );
    }
}
