<?php

namespace LaraDBChat\Console\Commands;

use Illuminate\Console\Command;
use LaraDBChat\Services\LaraDBChatService;
use LaraDBChat\Services\ModelAnalyzer;

class TrainCommand extends Command
{
    protected $signature = 'laradbchat:train
                            {--fresh : Clear existing training data before training}
                            {--skip-models : Skip analyzing Laravel models}
                            {--skip-migrations : Skip analyzing migrations}
                            {--show-schema : Display the extracted schema}
                            {--deep : Perform deep analysis including models and migrations (recommended)}
                            {--no-docs-prompt : Skip the documentation prompt after training}';

    protected $description = 'Train LaraDBChat on your database schema, models, and migrations';

    public function handle(LaraDBChatService $service): int
    {
        $this->info('Training LaraDBChat...');
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

        // Determine if we should do deep analysis
        $deepAnalysis = $this->option('deep');

        // If --deep not specified and no skip options given, ask the user
        if (!$deepAnalysis && !$this->option('skip-models') && !$this->option('skip-migrations')) {
            $deepAnalysis = $this->confirm(
                'Would you like to analyze Laravel Models and Migrations for better accuracy?',
                true
            );
        }

        // Train database schema
        $this->info('Step 1/3: Extracting database schema...');
        $result = $this->trainDatabaseSchema($service);

        // Analyze models and migrations if requested
        if ($deepAnalysis) {
            $this->newLine();
            $this->trainFromModelsAndMigrations($service);
        }

        // Show final status
        $this->newLine();
        $this->showTrainingStatus($service);

        // Prompt for additional documentation (unless skipped)
        if (!$this->option('no-docs-prompt')) {
            $this->newLine();
            if ($this->confirm('Would you like to add custom business documentation for better accuracy?', false)) {
                $this->call('laradbchat:add-docs');
            }
        }

        $this->newLine();
        $this->info('Training complete! Try asking questions with:');
        $this->line('  php artisan laradbchat:ask "Your question here"');

        return $result['success'] ? self::SUCCESS : self::FAILURE;
    }

    protected function trainDatabaseSchema(LaraDBChatService $service): array
    {
        $this->info('This may take a few minutes depending on your database size.');
        $this->newLine();

        try {
            $schema = $service->getSchema();
            $this->line("Found " . count($schema) . " tables.");

            $result = $service->train();

            if ($result['success']) {
                $this->info("Database schema trained successfully!");
            } else {
                $this->warn("Schema training completed with some errors.");
                if (!empty($result['errors'])) {
                    foreach ($result['errors'] as $table => $error) {
                        $this->line("  - {$table}: {$error}");
                    }
                }
            }

            return $result;

        } catch (\Exception $e) {
            $this->error("Schema training failed: {$e->getMessage()}");
            return ['success' => false, 'errors' => [$e->getMessage()]];
        }
    }

    protected function trainFromModelsAndMigrations(LaraDBChatService $service): void
    {
        $analyzer = new ModelAnalyzer();

        // Show detected table prefix
        $prefix = $analyzer->getTablePrefix();
        if ($prefix) {
            $this->line("Detected table prefix: '{$prefix}'");
        }

        // Analyze Models
        if (!$this->option('skip-models')) {
            $this->info('Step 2/3: Analyzing Laravel Models...');

            try {
                $models = $analyzer->analyzeModels();

                if (empty($models)) {
                    $this->warn('No models found in app/Models directory.');
                } else {
                    $this->line("Found " . count($models) . " models.");

                    foreach ($models as $model) {
                        $doc = $analyzer->generateModelDocumentation($model);
                        $service->addDocumentation(
                            "Model: {$model['model']}",
                            $doc
                        );
                    }

                    $this->info("Model documentation added successfully!");

                    // Generate relationship summary
                    $relationshipDoc = $this->generateRelationshipSummary($models);
                    if ($relationshipDoc) {
                        $service->addDocumentation('Table Relationships Summary', $relationshipDoc);
                    }
                }
            } catch (\Exception $e) {
                $this->warn("Model analysis failed: {$e->getMessage()}");
            }
        }

        // Analyze Migrations
        if (!$this->option('skip-migrations')) {
            $this->info('Step 3/3: Analyzing Migrations...');

            try {
                $migrations = $analyzer->analyzeMigrations();

                if (empty($migrations)) {
                    $this->warn('No migrations found.');
                } else {
                    $this->line("Found " . count($migrations) . " migrations.");

                    // Generate enum documentation
                    $enumDoc = $this->generateEnumDocumentation($migrations);
                    if ($enumDoc) {
                        $service->addDocumentation('Enum Values and Status Fields', $enumDoc);
                        $this->info("Enum documentation added successfully!");
                    }

                    // Generate foreign key documentation
                    $fkDoc = $analyzer->generateMigrationDocumentation($migrations);
                    if ($fkDoc) {
                        $service->addDocumentation('Foreign Key Relationships', $fkDoc);
                    }
                }
            } catch (\Exception $e) {
                $this->warn("Migration analysis failed: {$e->getMessage()}");
            }
        }
    }

    protected function generateRelationshipSummary(array $models): ?string
    {
        $doc = "This section explains how tables are related:\n\n";
        $hasRelationships = false;

        foreach ($models as $model) {
            if (empty($model['relationships'])) {
                continue;
            }

            $hasRelationships = true;
            foreach ($model['relationships'] as $rel) {
                $relType = match ($rel['type']) {
                    'belongsTo' => "belongs to (has foreign key to)",
                    'hasMany' => "has many",
                    'hasOne' => "has one",
                    'belongsToMany' => "has many-to-many relationship with",
                    default => $rel['type'],
                };

                $doc .= "- {$model['table']} {$relType} {$rel['related']} ";
                $doc .= "(access via ->{$rel['method']})\n";
            }
        }

        return $hasRelationships ? $doc : null;
    }

    protected function generateEnumDocumentation(array $migrations): ?string
    {
        $enums = [];

        foreach ($migrations as $migration) {
            if (!empty($migration['enums'])) {
                foreach ($migration['enums'] as $column => $values) {
                    $key = "{$migration['table']}.{$column}";
                    $enums[$key] = $values;
                }
            }
        }

        if (empty($enums)) {
            return null;
        }

        $doc = "These columns have specific allowed values (enums):\n\n";
        foreach ($enums as $key => $values) {
            $doc .= "- {$key}: " . implode(', ', array_map(fn($v) => "'{$v}'", $values)) . "\n";
        }

        $doc .= "\nWhen filtering by these columns, use the exact values listed above.";

        return $doc;
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
