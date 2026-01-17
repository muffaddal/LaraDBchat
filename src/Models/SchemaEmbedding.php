<?php

namespace LaraDBChat\Models;

use Illuminate\Database\Eloquent\Model;

class SchemaEmbedding extends Model
{
    protected $table = 'laradbchat_embeddings';

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
        'type',
        'identifier',
        'content',
        'embedding',
        'metadata',
    ];

    protected $casts = [
        'embedding' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Scope for table schemas.
     */
    public function scopeTables($query)
    {
        return $query->where('type', 'table');
    }

    /**
     * Scope for descriptions.
     */
    public function scopeDescriptions($query)
    {
        return $query->where('type', 'description');
    }

    /**
     * Scope for sample queries.
     */
    public function scopeSamples($query)
    {
        return $query->where('type', 'sample');
    }

    /**
     * Scope for documentation.
     */
    public function scopeDocumentation($query)
    {
        return $query->where('type', 'documentation');
    }

    /**
     * Scope by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
