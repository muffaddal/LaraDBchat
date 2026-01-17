<?php

namespace LaraDBChat\Database;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MigrationRunner
{
    /**
     * Run the LaraDBChat migrations on the specified connection.
     */
    public static function run(?string $connection = null): void
    {
        $connection = self::resolveConnection($connection);

        self::createEmbeddingsTable($connection);
        self::createQueryLogsTable($connection);
    }

    /**
     * Rollback (drop) the LaraDBChat tables from the specified connection.
     */
    public static function rollback(?string $connection = null): void
    {
        $connection = self::resolveConnection($connection);

        Schema::connection($connection)->dropIfExists('laradbchat_query_logs');
        Schema::connection($connection)->dropIfExists('laradbchat_embeddings');
    }

    /**
     * Check if the LaraDBChat tables exist.
     */
    public static function tablesExist(?string $connection = null): bool
    {
        $connection = self::resolveConnection($connection);

        return Schema::connection($connection)->hasTable('laradbchat_embeddings')
            && Schema::connection($connection)->hasTable('laradbchat_query_logs');
    }

    /**
     * Resolve the storage connection to use.
     */
    protected static function resolveConnection(?string $connection): ?string
    {
        if ($connection !== null) {
            return $connection;
        }

        // Check storage connection first
        $storageConnection = config('laradbchat.storage.connection');
        if ($storageConnection !== null) {
            return $storageConnection;
        }

        // Fall back to main connection
        $mainConnection = config('laradbchat.connection');
        if ($mainConnection !== null) {
            return $mainConnection;
        }

        // Use default connection (return null to use Laravel's default)
        return null;
    }

    /**
     * Create the embeddings table.
     */
    protected static function createEmbeddingsTable(?string $connection): void
    {
        if (Schema::connection($connection)->hasTable('laradbchat_embeddings')) {
            return;
        }

        Schema::connection($connection)->create('laradbchat_embeddings', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index();
            $table->string('identifier')->index();
            $table->text('content');
            $table->json('embedding');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['type', 'identifier']);
        });
    }

    /**
     * Create the query logs table.
     */
    protected static function createQueryLogsTable(?string $connection): void
    {
        if (Schema::connection($connection)->hasTable('laradbchat_query_logs')) {
            return;
        }

        Schema::connection($connection)->create('laradbchat_query_logs', function (Blueprint $table) {
            $table->id();
            $table->text('question');
            $table->text('generated_sql');
            $table->json('results')->nullable();
            $table->integer('result_count')->nullable();
            $table->float('execution_time')->nullable();
            $table->text('error')->nullable();
            $table->string('status')->default('success');
            $table->string('llm_provider')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('created_at');
            $table->index('status');
        });
    }

    /**
     * Ensure the SQLite database file exists if using laradbchat_sqlite connection.
     */
    public static function ensureSqliteDatabase(): void
    {
        $storageConnection = config('laradbchat.storage.connection');

        if ($storageConnection !== 'laradbchat_sqlite') {
            return;
        }

        $sqlitePath = config('laradbchat.storage.sqlite_path');

        if (empty($sqlitePath)) {
            $sqlitePath = storage_path('laradbchat/database.sqlite');
        }

        // Ensure directory exists
        $directory = dirname($sqlitePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // Create empty file if doesn't exist
        if (!file_exists($sqlitePath)) {
            touch($sqlitePath);
        }
    }
}
