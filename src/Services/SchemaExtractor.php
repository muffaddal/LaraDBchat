<?php

namespace LaraDBChat\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SchemaExtractor
{
    protected string $connection;
    protected array $config;

    public function __construct(?string $connection = null, array $config = [])
    {
        $this->connection = $connection ?? config('database.default');
        $this->config = array_merge([
            'include_indexes' => true,
            'include_foreign_keys' => true,
            'exclude_tables' => [],
        ], $config);
    }

    /**
     * Extract complete schema information from the database.
     */
    public function extract(): array
    {
        $tables = $this->getTables();
        $schema = [];

        foreach ($tables as $table) {
            if ($this->shouldExcludeTable($table)) {
                continue;
            }

            $schema[$table] = [
                'name' => $table,
                'columns' => $this->getColumns($table),
                'indexes' => $this->config['include_indexes'] ? $this->getIndexes($table) : [],
                'foreign_keys' => $this->config['include_foreign_keys'] ? $this->getForeignKeys($table) : [],
                'ddl' => $this->generateDDL($table),
            ];
        }

        return $schema;
    }

    /**
     * Get all table names from the database.
     */
    public function getTables(): array
    {
        $connection = DB::connection($this->connection);
        $driver = $connection->getDriverName();

        return match ($driver) {
            'mysql' => $this->getMySQLTables($connection),
            'pgsql' => $this->getPostgresTables($connection),
            'sqlite' => $this->getSQLiteTables($connection),
            'sqlsrv' => $this->getSQLServerTables($connection),
            default => Schema::connection($this->connection)->getTableListing(),
        };
    }

    protected function getMySQLTables($connection): array
    {
        $tables = $connection->select("SHOW TABLES");

        return array_map(function ($table) {
            // SHOW TABLES returns object with dynamic column name like "Tables_in_dbname"
            $values = array_values((array) $table);
            return $values[0] ?? '';
        }, $tables);
    }

    protected function getPostgresTables($connection): array
    {
        $tables = $connection->select(
            "SELECT tablename FROM pg_tables WHERE schemaname = 'public'"
        );

        return array_column($tables, 'tablename');
    }

    protected function getSQLiteTables($connection): array
    {
        $tables = $connection->select(
            "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"
        );

        return array_column($tables, 'name');
    }

    protected function getSQLServerTables($connection): array
    {
        $tables = $connection->select(
            "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE'"
        );

        return array_column($tables, 'TABLE_NAME');
    }

    /**
     * Get column information for a table.
     */
    public function getColumns(string $table): array
    {
        $columns = Schema::connection($this->connection)->getColumns($table);

        return array_map(function ($column) {
            return [
                'name' => $column['name'],
                'type' => $column['type'],
                'nullable' => $column['nullable'],
                'default' => $column['default'],
                'auto_increment' => $column['auto_increment'] ?? false,
                'comment' => $column['comment'] ?? null,
            ];
        }, $columns);
    }

    /**
     * Get index information for a table.
     */
    public function getIndexes(string $table): array
    {
        try {
            $indexes = Schema::connection($this->connection)->getIndexes($table);

            return array_map(function ($index) {
                return [
                    'name' => $index['name'],
                    'columns' => $index['columns'],
                    'unique' => $index['unique'],
                    'primary' => $index['primary'] ?? false,
                ];
            }, $indexes);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get foreign key information for a table.
     */
    public function getForeignKeys(string $table): array
    {
        try {
            $foreignKeys = Schema::connection($this->connection)->getForeignKeys($table);

            return array_map(function ($fk) {
                return [
                    'name' => $fk['name'],
                    'columns' => $fk['columns'],
                    'foreign_table' => $fk['foreign_table'],
                    'foreign_columns' => $fk['foreign_columns'],
                    'on_update' => $fk['on_update'] ?? null,
                    'on_delete' => $fk['on_delete'] ?? null,
                ];
            }, $foreignKeys);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Generate a DDL-like statement for a table.
     */
    public function generateDDL(string $table): string
    {
        $columns = $this->getColumns($table);
        $foreignKeys = $this->config['include_foreign_keys'] ? $this->getForeignKeys($table) : [];

        $ddl = "CREATE TABLE {$table} (\n";

        $columnDefs = [];
        foreach ($columns as $column) {
            $def = "  {$column['name']} {$column['type']}";

            if (!$column['nullable']) {
                $def .= ' NOT NULL';
            }

            if ($column['default'] !== null) {
                $def .= " DEFAULT {$column['default']}";
            }

            if (!empty($column['auto_increment'])) {
                $def .= ' AUTO_INCREMENT';
            }

            if (!empty($column['comment'])) {
                $def .= " COMMENT '{$column['comment']}'";
            }

            $columnDefs[] = $def;
        }

        $ddl .= implode(",\n", $columnDefs);

        // Add foreign key constraints
        if (!empty($foreignKeys)) {
            $ddl .= ",\n";
            $fkDefs = [];
            foreach ($foreignKeys as $fk) {
                $cols = implode(', ', $fk['columns']);
                $foreignCols = implode(', ', $fk['foreign_columns']);
                $fkDefs[] = "  FOREIGN KEY ({$cols}) REFERENCES {$fk['foreign_table']} ({$foreignCols})";
            }
            $ddl .= implode(",\n", $fkDefs);
        }

        $ddl .= "\n);";

        return $ddl;
    }

    /**
     * Generate a human-readable description of a table.
     */
    public function generateTableDescription(string $table): string
    {
        $columns = $this->getColumns($table);
        $foreignKeys = $this->config['include_foreign_keys'] ? $this->getForeignKeys($table) : [];

        $description = "Table: {$table}\n";
        $description .= "Columns:\n";

        foreach ($columns as $column) {
            $description .= "- {$column['name']} ({$column['type']})";
            if (!$column['nullable']) {
                $description .= " NOT NULL";
            }
            if (!empty($column['comment'])) {
                $description .= " - {$column['comment']}";
            }
            $description .= "\n";
        }

        if (!empty($foreignKeys)) {
            $description .= "Relationships:\n";
            foreach ($foreignKeys as $fk) {
                $cols = implode(', ', $fk['columns']);
                $foreignCols = implode(', ', $fk['foreign_columns']);
                $description .= "- {$cols} -> {$fk['foreign_table']}.{$foreignCols}\n";
            }
        }

        return $description;
    }

    /**
     * Check if a table should be excluded from extraction.
     */
    protected function shouldExcludeTable(string $table): bool
    {
        return in_array($table, $this->config['exclude_tables']);
    }

    /**
     * Get the database driver name.
     */
    public function getDriver(): string
    {
        return DB::connection($this->connection)->getDriverName();
    }

    /**
     * Get the database name.
     */
    public function getDatabaseName(): string
    {
        return DB::connection($this->connection)->getDatabaseName();
    }
}
