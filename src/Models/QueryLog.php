<?php

namespace LaraDBChat\Models;

use Illuminate\Database\Eloquent\Model;

class QueryLog extends Model
{
    protected $table = 'laradbchat_query_logs';

    /**
     * Get the database connection for the model.
     * Uses the configured storage connection for LaraDBChat data.
     */
    public function getConnectionName(): ?string
    {
        return config('laradbchat.storage.connection')
            ?? config('laradbchat.connection')
            ?? config('database.default');
    }

    protected $fillable = [
        'question',
        'generated_sql',
        'results',
        'result_count',
        'execution_time',
        'error',
        'status',
        'llm_provider',
        'metadata',
    ];

    protected $casts = [
        'results' => 'array',
        'metadata' => 'array',
        'execution_time' => 'float',
    ];

    /**
     * Scope for successful queries.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope for failed queries.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'error');
    }

    /**
     * Scope for queries by provider.
     */
    public function scopeByProvider($query, string $provider)
    {
        return $query->where('llm_provider', $provider);
    }

    /**
     * Scope for queries within date range.
     */
    public function scopeBetween($query, $from, $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }
}
