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
    | Storage Connection
    |--------------------------------------------------------------------------
    |
    | The database connection to use for storing LaraDBChat's internal data
    | (embeddings, query logs). If null, uses the same connection as 'connection'.
    | Set to 'laradbchat_sqlite' to use an auto-created SQLite database.
    |
    */
    'storage' => [
        // Storage connection name (null = use 'connection', 'laradbchat_sqlite' = auto SQLite)
        'connection' => env('LARADBCHAT_STORAGE_CONNECTION', null),

        // SQLite file path (used when connection is 'laradbchat_sqlite')
        'sqlite_path' => env('LARADBCHAT_SQLITE_PATH', storage_path('laradbchat/database.sqlite')),
    ],

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

        // Table filtering mode: 'all', 'exclude', or 'include'
        // 'all' - train all tables (ignore both lists)
        // 'exclude' - train all tables EXCEPT those in exclude_tables
        // 'include' - ONLY train tables in include_tables
        'table_mode' => env('LARADBCHAT_TABLE_MODE', 'exclude'),

        // Tables to exclude from training (used when table_mode = 'exclude')
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

        // Tables to include in training (used when table_mode = 'include')
        // When non-empty and table_mode = 'include', ONLY these tables are trained
        'include_tables' => [],

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

    /*
    |--------------------------------------------------------------------------
    | Web Widget Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the floating chat widget for web pages.
    |
    */
    'widget' => [
        // Enable/disable the widget
        'enabled' => env('LARADBCHAT_WIDGET_ENABLED', true),

        // Position: 'bottom-right', 'bottom-left', 'top-right', 'top-left'
        'position' => env('LARADBCHAT_WIDGET_POSITION', 'bottom-left'),

        // Theme colors
        'theme' => [
            'primary' => env('LARADBCHAT_WIDGET_PRIMARY', '#3B82F6'),
            'secondary' => env('LARADBCHAT_WIDGET_SECONDARY', '#1E40AF'),
            'background' => env('LARADBCHAT_WIDGET_BG', '#FFFFFF'),
            'text' => env('LARADBCHAT_WIDGET_TEXT', '#1F2937'),
        ],

        // Widget title
        'title' => env('LARADBCHAT_WIDGET_TITLE', 'Database Assistant'),

        // Placeholder text
        'placeholder' => env('LARADBCHAT_WIDGET_PLACEHOLDER', 'Ask a question about your data...'),

        // Show SQL in responses
        'show_sql' => env('LARADBCHAT_WIDGET_SHOW_SQL', true),

        // Maximum chat history to display
        'max_history' => env('LARADBCHAT_WIDGET_MAX_HISTORY', 50),
    ],
];
