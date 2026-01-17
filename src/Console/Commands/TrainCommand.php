<?php

namespace LaraDBChat\Console\Commands;

use Illuminate\Console\Command;
use LaraDBChat\Services\LaraDBChatService;
use LaraDBChat\Services\ModelAnalyzer;
use LaraDBChat\Services\SchemaExtractor;

class TrainCommand extends Command
{
    protected $signature = 'laradbchat:train
                            {--fresh : Clear existing training data before training}
                            {--skip-models : Skip analyzing Laravel models}
                            {--skip-migrations : Skip analyzing migrations}
                            {--show-schema : Display the extracted schema}
                            {--deep : Perform deep analysis including models and migrations (recommended)}
                            {--no-docs-prompt : Skip the documentation prompt after training}
                            {--only=* : Only train these tables (comma-separated or multiple flags)}
                            {--except=* : Exclude these tables from training (comma-separated or multiple flags)}
                            {--preview : Preview which tables will be trained without training}
                            {--select : Interactively select tables to train}';

    protected $description = 'Train LaraDBChat on your database schema, models, and migrations';

    public function handle(LaraDBChatService $service): int
    {
        // Get schema extractor and apply runtime filters
        $schemaExtractor = app(SchemaExtractor::class);

        // Parse table filters
        $onlyTables = $this->parseTableList($this->option('only'));
        $exceptTables = $this->parseTableList($this->option('except'));

        // Interactive selection mode
        if ($this->option('select')) {
            $onlyTables = $this->interactiveTableSelection($schemaExtractor);
            if (empty($onlyTables)) {
                $this->warn('No tables selected. Training cancelled.');
                return self::SUCCESS;
            }
        }

        // Apply runtime filters
        $schemaExtractor->setRuntimeFilters($onlyTables, $exceptTables);

        // Preview mode - show tables without training
        if ($this->option('preview')) {
            return $this->showPreview($schemaExtractor);
        }

        // Show training summary and confirm
        $trainable = $schemaExtractor->getTrainableTables();
        $excluded = $schemaExtractor->getExcludedTables();

        if (empty($trainable)) {
            $this->error('No tables to train! Check your include/exclude settings.');
            return self::FAILURE;
        }

        $this->showTableSummary($trainable, $excluded, $schemaExtractor->getTableMode());

        if (!$this->option('no-interaction') && !$this->confirm('Proceed with training?', true)) {
            $this->info('Training cancelled.');
            return self::SUCCESS;
        }

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

    /**
     * Parse table list from CLI options (supports comma-separated and multiple flags).
     */
    protected function parseTableList(array $options): array
    {
        $tables = [];
        foreach ($options as $option) {
            // Support both comma-separated and multiple flags
            $tables = array_merge($tables, array_map('trim', explode(',', $option)));
        }
        return array_filter($tables);
    }

    /**
     * Show preview of tables that will be trained.
     */
    protected function showPreview(SchemaExtractor $extractor): int
    {
        $trainable = $extractor->getTrainableTables();
        $excluded = $extractor->getExcludedTables();

        $this->info('LaraDBChat Training Preview');
        $this->line('Mode: ' . $extractor->getTableMode());
        $this->newLine();

        $this->info('Tables that WILL be trained (' . count($trainable) . '):');
        if (empty($trainable)) {
            $this->warn('  No tables will be trained!');
        } else {
            $this->table(['Table Name'], array_map(fn($t) => [$t], $trainable));
        }

        $this->newLine();
        $this->warn('Tables that will be EXCLUDED (' . count($excluded) . '):');
        if (empty($excluded)) {
            $this->line('  No tables excluded.');
        } else {
            $this->table(['Table Name'], array_map(fn($t) => [$t], $excluded));
        }

        return self::SUCCESS;
    }

    /**
     * Show summary of tables to be trained.
     */
    protected function showTableSummary(array $trainable, array $excluded, string $mode): void
    {
        $this->info('Training Summary');
        $this->line("Mode: {$mode}");
        $this->line("Tables to train: " . count($trainable));
        $this->line("Tables excluded: " . count($excluded));

        if (count($trainable) <= 15) {
            $this->line("Training: " . implode(', ', $trainable));
        } else {
            $this->line("Training: " . implode(', ', array_slice($trainable, 0, 10)) . '... and ' . (count($trainable) - 10) . ' more');
        }
        $this->newLine();
    }

    /**
     * Interactive table selection.
     */
    protected function interactiveTableSelection(SchemaExtractor $extractor): array
    {
        $allTables = $extractor->getTables();
        $defaultSelected = $extractor->getTrainableTables();

        if (empty($allTables)) {
            $this->error('No tables found in the database.');
            return [];
        }

        $this->info('Interactive Table Selection');
        $this->line("Found " . count($allTables) . " tables in the database.");
        $this->newLine();

        // Show current defaults
        $this->line("Default selection: " . count($defaultSelected) . " tables (based on config)");
        $this->newLine();

        // Use Laravel's choice with multiple selection
        $selected = $this->choice(
            'Select tables to train (comma-separated numbers, or "all" for all tables)',
            array_merge(['all' => '** Select All Tables **'], array_combine($allTables, $allTables)),
            null,
            null,
            true // multiple selection
        );

        // Handle "all" selection
        if (in_array('all', $selected) || in_array('** Select All Tables **', $selected)) {
            return $allTables;
        }

        return $selected;
    }

    protected function trainDatabaseSchema(LaraDBChatService $service): array
    {
        $this->info('This may take a few minutes depending on your database size.');
        $this->newLine();

        try {
            $schema = $service->getSchema();
            $this->line("Found " . count($schema) . " tables to train.");

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
