# LaraDBChat

Transform your Laravel database into a smart assistant using natural language queries.

LaraDBChat is a Laravel package that enables you to query your database using natural language. It converts your questions into SQL queries using AI (supports Ollama, OpenAI, and Claude) and returns the results.

## Features

- **Natural Language Queries**: Ask questions in plain English like "Show me all users who signed up this week"
- **Multiple LLM Providers**: Choose between Ollama (local/free), OpenAI, or Claude
- **Database Agnostic**: Works with MySQL, PostgreSQL, SQLite, and SQL Server
- **Auto-Training**: Automatically extracts and learns your database schema
- **Safe by Default**: Read-only mode prevents accidental data modifications
- **Query Logging**: Track all queries with file-based or database logging
- **API & CLI**: Use via REST API or Artisan commands
- **Web UI Widget**: Floating chat widget for your web pages (Alpine.js + CSS)
- **Separate Storage**: Store embeddings/logs in a separate database (SQLite support)
- **Table Filtering**: Include/exclude specific tables during training

## Requirements

- PHP 8.1+
- Laravel 10.x or 11.x
- One of the following LLM providers:
  - [Ollama](https://ollama.ai) (local, free)
  - OpenAI API key
  - Anthropic (Claude) API key

## Installation

```bash
composer require laradbchat/laradbchat
```

Run the installation command:

```bash
php artisan laradbchat:install
```

This will:
1. Publish the configuration file
2. Run the database migrations

## Configuration

### LLM Provider Setup

Add the following to your `.env` file based on your chosen provider:

#### Option 1: Ollama (Local, Free)

```env
LARADBCHAT_LLM_PROVIDER=ollama
OLLAMA_HOST=http://localhost:11434
OLLAMA_MODEL=qwen2.5-coder:3b
OLLAMA_EMBEDDING_MODEL=nomic-embed-text
```

First, install Ollama and pull the required models:

```bash
# Install Ollama (see https://ollama.ai)
# Then pull the models:
ollama pull qwen2.5-coder:3b
ollama pull nomic-embed-text
```

#### Option 2: OpenAI

```env
LARADBCHAT_LLM_PROVIDER=openai
OPENAI_API_KEY=your-api-key-here
OPENAI_MODEL=gpt-4o
OPENAI_EMBEDDING_MODEL=text-embedding-3-small
```

#### Option 3: Claude (Anthropic)

```env
LARADBCHAT_LLM_PROVIDER=claude
ANTHROPIC_API_KEY=your-api-key-here
CLAUDE_MODEL=claude-sonnet-4-20250514
```

### Additional Configuration

```env
# Database connection (optional, defaults to your app's default connection)
LARADBCHAT_CONNECTION=mysql

# Query execution settings
LARADBCHAT_EXECUTE=true          # Enable/disable query execution
LARADBCHAT_READ_ONLY=true        # Only allow SELECT queries
LARADBCHAT_MAX_RESULTS=100       # Maximum rows to return
LARADBCHAT_TIMEOUT=30            # Query timeout in seconds

# Logging
LARADBCHAT_LOGGING=true          # Enable/disable logging
LARADBCHAT_LOG_DRIVER=file       # 'file' or 'database'

# API
LARADBCHAT_API_ENABLED=true      # Enable/disable REST API
```

## Usage

### Training

Before asking questions, train LaraDBChat on your database schema:

```bash
php artisan laradbchat:train
```

#### Deep Analysis (Recommended)

For better accuracy, use deep analysis which examines your Laravel Models and Migrations:

```bash
php artisan laradbchat:train --deep
```

This extracts:
- Model relationships (belongsTo, hasMany, etc.)
- Scopes and constants
- Foreign key constraints
- Enum values from migrations

#### Training Options

```bash
# Fresh training (clears existing data)
php artisan laradbchat:train --fresh

# Deep analysis with models and migrations
php artisan laradbchat:train --deep

# Skip specific analysis
php artisan laradbchat:train --skip-models
php artisan laradbchat:train --skip-migrations

# Show extracted schema
php artisan laradbchat:train --show-schema

# Train only specific tables
php artisan laradbchat:train --only=users,orders,products

# Exclude specific tables
php artisan laradbchat:train --except=logs,cache,sessions

# Preview which tables will be trained (without training)
php artisan laradbchat:train --preview

# Interactive table selection
php artisan laradbchat:train --select
```

### Adding Business Documentation

Improve accuracy by adding context about your database:

```bash
# Interactive documentation
php artisan laradbchat:add-docs

# Add sample queries
php artisan laradbchat:add-docs --sample

# Import from JSON file
php artisan laradbchat:add-docs --file=training.json
```

Example JSON training file:
```json
{
    "documentation": [
        {
            "title": "Order Status Values",
            "content": "The status column can be: pending, confirmed, shipped, delivered"
        }
    ],
    "samples": [
        {
            "question": "Show pending orders",
            "sql": "SELECT * FROM orders WHERE status = 'pending'"
        }
    ]
}
```

### Artisan Commands

#### Ask Questions

```bash
# Simple query
php artisan laradbchat:ask "Show all users"

# Generate SQL only (don't execute)
php artisan laradbchat:ask "Count orders by status" --sql-only

# Output as JSON
php artisan laradbchat:ask "Top 5 products by sales" --json

# Interactive mode
php artisan laradbchat:ask --interactive
```

### Using the Facade

```php
use LaraDBChat\Facades\LaraDBChat;

// Ask a question and get results
$result = LaraDBChat::ask('How many users signed up this month?');

// Generate SQL only
$sql = LaraDBChat::generateSql('Show all active subscriptions');

// Train the system
$result = LaraDBChat::train();

// Get query history
$history = LaraDBChat::getHistory(limit: 10);

// Add a sample query for better results
LaraDBChat::addSampleQuery(
    'Show revenue by month',
    'SELECT DATE_FORMAT(created_at, "%Y-%m") as month, SUM(total) as revenue FROM orders GROUP BY month'
);
```

### Using Dependency Injection

```php
use LaraDBChat\Services\LaraDBChatService;

class ReportController extends Controller
{
    public function __construct(
        private LaraDBChatService $chat
    ) {}

    public function query(Request $request)
    {
        $result = $this->chat->ask($request->input('question'));

        return response()->json($result);
    }
}
```

### REST API

#### Ask a Question

```bash
POST /api/laradbchat/ask
Content-Type: application/json

{
    "question": "Show me all users who signed up this week",
    "execute": true
}
```

Response:
```json
{
    "success": true,
    "question": "Show me all users who signed up this week",
    "sql": "SELECT * FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
    "data": [...],
    "count": 15,
    "execution_time": 0.023,
    "total_time": 1.245,
    "provider": "ollama"
}
```

#### Train the System

```bash
POST /api/laradbchat/train
Content-Type: application/json

{
    "fresh": false
}
```

#### Get Query History

```bash
GET /api/laradbchat/history?limit=10&offset=0
```

#### Get Training Status

```bash
GET /api/laradbchat/status
```

#### Get Database Schema

```bash
GET /api/laradbchat/schema
```

#### Add Sample Query

```bash
POST /api/laradbchat/samples
Content-Type: application/json

{
    "question": "Show monthly revenue",
    "sql": "SELECT MONTH(created_at) as month, SUM(total) as revenue FROM orders GROUP BY month"
}
```

## Web UI Widget

LaraDBChat includes a floating chat widget you can add to any page.

### Setup

1. Publish the assets:
```bash
php artisan vendor:publish --tag=laradbchat-assets
```

2. Include the CSS, JS, and Alpine.js in your layout:
```blade
<head>
    <!-- LaraDBChat CSS -->
    <link rel="stylesheet" href="{{ asset('vendor/laradbchat/css/laradbchat.css') }}">

    <!-- Alpine.js (if not already included) -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- LaraDBChat JS -->
    <script src="{{ asset('vendor/laradbchat/js/laradbchat.js') }}"></script>
</head>
```

3. Add the widget component to your layout:
```blade
<x-laradbchat-widget />
```

### Widget Options

```blade
<!-- Default position (bottom-right) -->
<x-laradbchat-widget />

<!-- Custom position -->
<x-laradbchat-widget position="bottom-left" />
<x-laradbchat-widget position="top-right" />

<!-- Hide SQL in responses -->
<x-laradbchat-widget :show-sql="false" />

<!-- Custom title and placeholder -->
<x-laradbchat-widget
    title="Data Assistant"
    placeholder="Ask me anything..."
/>
```

### Widget Configuration

Configure the widget in your `.env` or `config/laradbchat.php`:

```env
LARADBCHAT_WIDGET_ENABLED=true
LARADBCHAT_WIDGET_POSITION=bottom-right
LARADBCHAT_WIDGET_SHOW_SQL=true
LARADBCHAT_WIDGET_TITLE="Database Assistant"
LARADBCHAT_WIDGET_PRIMARY=#3B82F6
```

## Separate Storage Connection

Store LaraDBChat's internal data (embeddings, query logs) in a separate database to keep your main database clean.

### SQLite Storage (Recommended)

```env
LARADBCHAT_STORAGE_CONNECTION=laradbchat_sqlite
LARADBCHAT_SQLITE_PATH=/path/to/storage/laradbchat/database.sqlite
```

The SQLite file is auto-created if it doesn't exist.

### Custom Database Connection

```env
LARADBCHAT_STORAGE_CONNECTION=laradbchat_mysql
```

Then define the connection in `config/database.php`:
```php
'laradbchat_mysql' => [
    'driver' => 'mysql',
    'host' => env('LARADBCHAT_DB_HOST', '127.0.0.1'),
    'database' => env('LARADBCHAT_DB_DATABASE', 'laradbchat'),
    // ...
],
```

### Run Migrations

After configuring storage, run migrations on the correct connection:
```bash
php artisan laradbchat:migrate
```

## Configuration Options

Publish the config file:

```bash
php artisan vendor:publish --tag=laradbchat-config
```

Key configuration options in `config/laradbchat.php`:

```php
return [
    'llm' => [
        'provider' => env('LARADBCHAT_LLM_PROVIDER', 'ollama'),
        // Provider-specific settings...
    ],

    // Separate storage for embeddings and logs
    'storage' => [
        'connection' => env('LARADBCHAT_STORAGE_CONNECTION', null),
        'sqlite_path' => storage_path('laradbchat/database.sqlite'),
    ],

    'execution' => [
        'enabled' => true,      // Execute generated queries
        'read_only' => true,    // Only allow SELECT
        'max_results' => 100,   // Limit results
        'timeout' => 30,        // Query timeout
    ],

    'logging' => [
        'enabled' => true,
        'driver' => 'file',     // 'file' or 'database'
    ],

    'training' => [
        'table_mode' => 'exclude',  // 'all', 'exclude', or 'include'
        'exclude_tables' => [       // Tables to skip (when mode = 'exclude')
            'migrations',
            'password_resets',
            'sessions',
        ],
        'include_tables' => [],     // Tables to train (when mode = 'include')
    ],

    'widget' => [
        'enabled' => true,
        'position' => 'bottom-right',
        'show_sql' => true,
        'theme' => [
            'primary' => '#3B82F6',
            'secondary' => '#1E40AF',
        ],
    ],
];
```

## Example Queries

Here are some example natural language queries you can ask:

```
- "Show all users"
- "How many orders were placed this month?"
- "List the top 10 products by sales"
- "Show users who haven't made a purchase"
- "What's the average order value?"
- "Count users by country"
- "Show orders with their customer names"
- "Find products that are out of stock"
- "Show revenue trend for the last 6 months"
- "List customers with more than 5 orders"
```

## Security

LaraDBChat includes several security features:

1. **Read-Only Mode**: By default, only SELECT queries are allowed
2. **Query Validation**: Dangerous patterns (DROP, DELETE, etc.) are blocked
3. **Result Limiting**: Maximum results are enforced
4. **Timeout Protection**: Queries are terminated after the configured timeout
5. **No System Variables**: Access to system variables is blocked

## Troubleshooting

### "Failed to connect to Ollama"

Make sure Ollama is running:
```bash
ollama serve
```

### "No embeddings found"

Run the training command:
```bash
php artisan laradbchat:train
```

### Inaccurate SQL Generation

1. Train with fresh data: `php artisan laradbchat:train --fresh`
2. Add sample queries for your common use cases
3. Consider using a more capable model (e.g., GPT-4 or Claude)

## License

MIT License. See [LICENSE](LICENSE) for more information.

## Contributing

Contributions are welcome! Please see [CONTRIBUTING](CONTRIBUTING.md) for details.
