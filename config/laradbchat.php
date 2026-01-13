<?php

return [
    /*
    |--------------------------------------------------------------------------
    | LLM Provider Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which LLM provider to use for text-to-SQL conversion.
    | Supported: "ollama", "openai", "claude"
    |
    */
    'llm' => [
        'provider' => env('LARADBCHAT_LLM_PROVIDER', 'ollama'),

        'ollama' => [
            'host' => env('OLLAMA_HOST', 'http://localhost:11434'),
            'model' => env('OLLAMA_MODEL', 'qwen2.5-coder:3b'),
            'embedding_model' => env('OLLAMA_EMBEDDING_MODEL', 'nomic-embed-text'),
            'timeout' => env('OLLAMA_TIMEOUT', 120),
        ],

        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o'),
            'embedding_model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
            'timeout' => env('OPENAI_TIMEOUT', 60),
        ],

        'claude' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('CLAUDE_MODEL', 'claude-sonnet-4-20250514'),
            'timeout' => env('CLAUDE_TIMEOUT', 60),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Connection
    |--------------------------------------------------------------------------
    |
    | The database connection to use for querying. If null, the default
    | database connection will be used.
    |
    */
    'connection' => env('LARADBCHAT_CONNECTION', null),

    /*
    |--------------------------------------------------------------------------
    | Query Execution Settings
    |--------------------------------------------------------------------------
    |
    | Configure how queries are executed after SQL generation.
    |
    */
    'execution' => [
        // Whether to execute generated SQL queries
        'enabled' => env('LARADBCHAT_EXECUTE', true),

        // Only allow SELECT queries (recommended for safety)
        'read_only' => env('LARADBCHAT_READ_ONLY', true),

        // Maximum number of results to return
        'max_results' => env('LARADBCHAT_MAX_RESULTS', 100),

        // Query timeout in seconds
        'timeout' => env('LARADBCHAT_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how queries and results are logged.
    |
    */
    'logging' => [
        // Enable/disable logging
        'enabled' => env('LARADBCHAT_LOGGING', true),

        // Logging driver: "file" or "database"
        'driver' => env('LARADBCHAT_LOG_DRIVER', 'file'),

        // Log channel name (for file driver)
        'channel' => env('LARADBCHAT_LOG_CHANNEL', 'laradbchat'),

        // Log file path (if using custom file location)
        'path' => storage_path('logs/laradbchat.log'),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the REST API endpoints.
    |
    */
    'api' => [
        // Enable/disable API routes
        'enabled' => env('LARADBCHAT_API_ENABLED', true),

        // API route prefix
        'prefix' => 'api/laradbchat',

        // Middleware to apply to API routes
        'middleware' => ['api'],

        // Rate limiting (requests per minute)
        'rate_limit' => env('LARADBCHAT_RATE_LIMIT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Training Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how the schema extraction and training works.
    |
    */
    'training' => [
        // Include index information in schema
        'include_indexes' => true,

        // Include foreign key relationships
        'include_foreign_keys' => true,

        // Tables to exclude from training
        'exclude_tables' => [
            'migrations',
            'password_resets',
            'password_reset_tokens',
            'failed_jobs',
            'personal_access_tokens',
            'laradbchat_embeddings',
            'laradbchat_query_logs',
            'cache',
            'cache_locks',
            'sessions',
            'jobs',
            'job_batches',
        ],

        // Custom documentation to include in training
        'custom_documentation' => [],

        // Sample queries to include for few-shot learning
        'sample_queries' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Embedding Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the embedding storage and retrieval settings.
    |
    */
    'embeddings' => [
        // Number of similar schemas to retrieve for context
        'top_k' => env('LARADBCHAT_TOP_K', 5),

        // Minimum similarity threshold (0-1)
        'similarity_threshold' => env('LARADBCHAT_SIMILARITY_THRESHOLD', 0.3),
    ],
];
