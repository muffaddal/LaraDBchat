<?php

namespace LaraDBChat\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;

class ModelAnalyzer
{
    protected string $modelsPath;
    protected string $migrationsPath;
    protected string $tablePrefix;

    public function __construct(?string $modelsPath = null, ?string $migrationsPath = null, ?string $tablePrefix = null)
    {
        $this->modelsPath = $modelsPath ?? app_path('Models');
        $this->migrationsPath = $migrationsPath ?? database_path('migrations');

        // Get table prefix from database config if not provided
        if ($tablePrefix === null) {
            $connection = config('database.default');
            $this->tablePrefix = config("database.connections.{$connection}.prefix", '');
        } else {
            $this->tablePrefix = $tablePrefix;
        }
    }

    /**
     * Get the configured table prefix.
     */
    public function getTablePrefix(): string
    {
        return $this->tablePrefix;
    }

    /**
     * Analyze all models and generate documentation.
     */
    public function analyzeModels(): array
    {
        $documentation = [];
        $modelFiles = $this->getModelFiles();

        foreach ($modelFiles as $file) {
            $analysis = $this->analyzeModelFile($file);
            if ($analysis) {
                $documentation[] = $analysis;
            }
        }

        return $documentation;
    }

    /**
     * Get all PHP files in the Models directory.
     */
    protected function getModelFiles(): array
    {
        if (!File::isDirectory($this->modelsPath)) {
            return [];
        }

        return File::allFiles($this->modelsPath);
    }

    /**
     * Analyze a single model file.
     */
    protected function analyzeModelFile($file): ?array
    {
        $content = File::get($file->getPathname());

        // Extract namespace and class name
        $namespace = $this->extractNamespace($content);
        $className = $file->getFilenameWithoutExtension();
        $fullClassName = $namespace ? "{$namespace}\\{$className}" : $className;

        // Skip if not a valid Eloquent model
        if (!$this->isEloquentModel($content)) {
            return null;
        }

        $analysis = [
            'model' => $className,
            'table' => $this->extractTableName($content, $className),
            'relationships' => $this->extractRelationships($content),
            'fillable' => $this->extractArrayProperty($content, 'fillable'),
            'casts' => $this->extractArrayProperty($content, 'casts'),
            'scopes' => $this->extractScopes($content),
            'constants' => $this->extractConstants($content),
            'comments' => $this->extractClassComments($content),
        ];

        return $analysis;
    }

    /**
     * Check if the file is an Eloquent model.
     */
    protected function isEloquentModel(string $content): bool
    {
        return Str::contains($content, 'extends Model') ||
               Str::contains($content, 'use HasFactory') ||
               Str::contains($content, 'Illuminate\Database\Eloquent');
    }

    /**
     * Extract namespace from file content.
     */
    protected function extractNamespace(string $content): ?string
    {
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }

    /**
     * Extract table name from model (with prefix applied).
     */
    protected function extractTableName(string $content, string $className): string
    {
        $tableName = null;

        // Check for explicit $table property
        if (preg_match('/protected\s+\$table\s*=\s*[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
            $tableName = $matches[1];
        } else {
            // Default Laravel convention: snake_case plural
            $tableName = Str::snake(Str::plural($className));
        }

        // Apply prefix if configured and not already present
        if ($this->tablePrefix && !Str::startsWith($tableName, $this->tablePrefix)) {
            $tableName = $this->tablePrefix . $tableName;
        }

        return $tableName;
    }

    /**
     * Extract relationships from model.
     */
    protected function extractRelationships(string $content): array
    {
        $relationships = [];
        $relationshipTypes = [
            'belongsTo',
            'hasOne',
            'hasMany',
            'belongsToMany',
            'hasManyThrough',
            'hasOneThrough',
            'morphTo',
            'morphOne',
            'morphMany',
            'morphToMany',
            'morphedByMany',
        ];

        foreach ($relationshipTypes as $type) {
            // Match relationship method definitions
            $pattern = '/public\s+function\s+(\w+)\s*\([^)]*\)\s*(?::\s*\w+)?\s*\{[^}]*' . $type . '\s*\(\s*([^)]+)\)/s';

            if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $methodName = $match[1];
                    $args = $match[2];

                    // Extract related model
                    $relatedModel = $this->extractRelatedModel($args);

                    $relationships[] = [
                        'method' => $methodName,
                        'type' => $type,
                        'related' => $relatedModel,
                    ];
                }
            }
        }

        return $relationships;
    }

    /**
     * Extract related model from relationship arguments.
     */
    protected function extractRelatedModel(string $args): string
    {
        // Match Class::class or 'Class' or "Class"
        if (preg_match('/(\w+)::class/', $args, $matches)) {
            return $matches[1];
        }
        if (preg_match('/[\'"]([^\'"]+)[\'"]/', $args, $matches)) {
            return $matches[1];
        }
        return 'Unknown';
    }

    /**
     * Extract array property like $fillable or $casts.
     */
    protected function extractArrayProperty(string $content, string $property): array
    {
        $pattern = '/protected\s+\$' . $property . '\s*=\s*\[(.*?)\];/s';

        if (preg_match($pattern, $content, $matches)) {
            $arrayContent = $matches[1];

            // Extract string values
            preg_match_all('/[\'"]([^\'"]+)[\'"]/', $arrayContent, $values);

            if ($property === 'casts') {
                // For casts, extract key => value pairs
                $casts = [];
                preg_match_all('/[\'"]([^\'"]+)[\'"]\s*=>\s*[\'"]?([^\'",$]+)[\'"]?/', $arrayContent, $castMatches, PREG_SET_ORDER);
                foreach ($castMatches as $match) {
                    $casts[$match[1]] = trim($match[2]);
                }
                return $casts;
            }

            return $values[1] ?? [];
        }

        return [];
    }

    /**
     * Extract scope methods.
     */
    protected function extractScopes(string $content): array
    {
        $scopes = [];

        // Match scope methods (scopeActive, scopePublished, etc.)
        if (preg_match_all('/public\s+function\s+scope(\w+)\s*\(/', $content, $matches)) {
            $scopes = $matches[1];
        }

        return $scopes;
    }

    /**
     * Extract class constants.
     */
    protected function extractConstants(string $content): array
    {
        $constants = [];

        // Match const declarations
        if (preg_match_all('/(?:public\s+)?const\s+(\w+)\s*=\s*([^;]+);/', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $constants[$match[1]] = trim($match[2]);
            }
        }

        return $constants;
    }

    /**
     * Extract class-level comments/docblocks.
     */
    protected function extractClassComments(string $content): ?string
    {
        // Match class docblock
        if (preg_match('/\/\*\*(.*?)\*\/\s*class\s+\w+/s', $content, $matches)) {
            $comment = $matches[1];
            // Clean up the comment
            $comment = preg_replace('/^\s*\*\s?/m', '', $comment);
            $comment = trim($comment);
            return $comment ?: null;
        }

        return null;
    }

    /**
     * Analyze migrations and extract schema evolution.
     */
    public function analyzeMigrations(): array
    {
        $migrations = [];

        if (!File::isDirectory($this->migrationsPath)) {
            return [];
        }

        $files = File::files($this->migrationsPath);

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $content = File::get($file->getPathname());
            $analysis = $this->analyzeMigrationFile($file->getFilename(), $content);

            if ($analysis) {
                $migrations[] = $analysis;
            }
        }

        return $migrations;
    }

    /**
     * Analyze a single migration file.
     */
    protected function analyzeMigrationFile(string $filename, string $content): ?array
    {
        // Extract table name from Schema::create or Schema::table
        $tableName = null;
        if (preg_match('/Schema::create\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
            $tableName = $matches[1];
        } elseif (preg_match('/Schema::table\s*\(\s*[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
            $tableName = $matches[1];
        }

        if (!$tableName) {
            return null;
        }

        // Apply prefix if configured and not already present
        if ($this->tablePrefix && !Str::startsWith($tableName, $this->tablePrefix)) {
            $tableName = $this->tablePrefix . $tableName;
        }

        // Extract columns
        $columns = $this->extractMigrationColumns($content);

        // Extract foreign keys
        $foreignKeys = $this->extractMigrationForeignKeys($content);

        // Extract enums
        $enums = $this->extractMigrationEnums($content);

        return [
            'file' => $filename,
            'table' => $tableName,
            'columns' => $columns,
            'foreign_keys' => $foreignKeys,
            'enums' => $enums,
        ];
    }

    /**
     * Extract columns from migration.
     */
    protected function extractMigrationColumns(string $content): array
    {
        $columns = [];

        // Match column definitions like $table->string('name'), $table->integer('status')
        $pattern = '/\$table->(\w+)\s*\(\s*[\'"]([^\'"]+)[\'"]/';

        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $columns[] = [
                    'type' => $match[1],
                    'name' => $match[2],
                ];
            }
        }

        return $columns;
    }

    /**
     * Extract foreign key definitions.
     */
    protected function extractMigrationForeignKeys(string $content): array
    {
        $foreignKeys = [];

        // Match foreign key definitions
        $pattern = '/->foreign\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)\s*->references\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)\s*->on\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/';

        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $foreignKeys[] = [
                    'column' => $match[1],
                    'references' => $match[2],
                    'on' => $match[3],
                ];
            }
        }

        // Also match foreignId pattern
        $pattern2 = '/->foreignId\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)\s*->constrained\s*\(\s*[\'"]?([^\'")\s]+)?[\'"]?\s*\)/';
        if (preg_match_all($pattern2, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $column = $match[1];
                $table = $match[2] ?? Str::plural(Str::beforeLast($column, '_id'));
                $foreignKeys[] = [
                    'column' => $column,
                    'references' => 'id',
                    'on' => $table,
                ];
            }
        }

        return $foreignKeys;
    }

    /**
     * Extract enum definitions from migration.
     */
    protected function extractMigrationEnums(string $content): array
    {
        $enums = [];

        // Match enum definitions
        $pattern = '/->enum\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*\[(.*?)\]\s*\)/s';

        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $values = [];
                preg_match_all('/[\'"]([^\'"]+)[\'"]/', $match[2], $valueMatches);
                $values = $valueMatches[1] ?? [];

                $enums[$match[1]] = $values;
            }
        }

        return $enums;
    }

    /**
     * Generate documentation from model analysis.
     */
    public function generateModelDocumentation(array $modelAnalysis): string
    {
        $doc = "Model: {$modelAnalysis['model']}\n";
        $doc .= "Table: {$modelAnalysis['table']}\n\n";

        if (!empty($modelAnalysis['comments'])) {
            $doc .= "Description: {$modelAnalysis['comments']}\n\n";
        }

        if (!empty($modelAnalysis['relationships'])) {
            $doc .= "Relationships:\n";
            foreach ($modelAnalysis['relationships'] as $rel) {
                $doc .= "- {$modelAnalysis['model']} {$rel['type']} {$rel['related']} (via {$rel['method']}() method)\n";
            }
            $doc .= "\n";
        }

        if (!empty($modelAnalysis['scopes'])) {
            $doc .= "Available Scopes (query filters):\n";
            foreach ($modelAnalysis['scopes'] as $scope) {
                $doc .= "- scope{$scope} (use as ->$scope())\n";
            }
            $doc .= "\n";
        }

        if (!empty($modelAnalysis['constants'])) {
            $doc .= "Constants (possible values):\n";
            foreach ($modelAnalysis['constants'] as $name => $value) {
                $doc .= "- {$name} = {$value}\n";
            }
            $doc .= "\n";
        }

        if (!empty($modelAnalysis['casts'])) {
            $doc .= "Column Types:\n";
            foreach ($modelAnalysis['casts'] as $column => $type) {
                $doc .= "- {$column}: {$type}\n";
            }
        }

        return $doc;
    }

    /**
     * Generate documentation from migration analysis.
     */
    public function generateMigrationDocumentation(array $migrations): string
    {
        // Group migrations by table
        $tableInfo = [];

        foreach ($migrations as $migration) {
            $table = $migration['table'];
            if (!isset($tableInfo[$table])) {
                $tableInfo[$table] = [
                    'columns' => [],
                    'foreign_keys' => [],
                    'enums' => [],
                ];
            }

            $tableInfo[$table]['columns'] = array_merge(
                $tableInfo[$table]['columns'],
                $migration['columns']
            );
            $tableInfo[$table]['foreign_keys'] = array_merge(
                $tableInfo[$table]['foreign_keys'],
                $migration['foreign_keys']
            );
            $tableInfo[$table]['enums'] = array_merge(
                $tableInfo[$table]['enums'],
                $migration['enums']
            );
        }

        $doc = "";
        foreach ($tableInfo as $table => $info) {
            if (!empty($info['enums'])) {
                $doc .= "Table {$table} - Enum Values:\n";
                foreach ($info['enums'] as $column => $values) {
                    $doc .= "- Column '{$column}' can have values: " . implode(', ', $values) . "\n";
                }
                $doc .= "\n";
            }

            if (!empty($info['foreign_keys'])) {
                $doc .= "Table {$table} - Foreign Keys:\n";
                foreach ($info['foreign_keys'] as $fk) {
                    $doc .= "- {$fk['column']} references {$fk['on']}.{$fk['references']}\n";
                }
                $doc .= "\n";
            }
        }

        return $doc;
    }
}
