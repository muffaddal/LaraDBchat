# Changelog

All notable changes to LaraDBChat will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.1] - 2025-01-17

### Added

- **Automatic Content Chunking**: Large content (models, migrations, documentation) is now automatically split into overlapping chunks
  - Handles embedding model context length limits (e.g., nomic-embed-text ~8k tokens)
  - Configurable `max_chunk_size` and `chunk_overlap` settings
  - Smart chunk boundaries at paragraphs, newlines, or sentences
  - Individual model processing with graceful error handling

### Changed

- `addDocumentation()` now uses `storeChunked()` for automatic chunking
- Improved error handling in model/migration analysis with per-item try/catch
- Better progress reporting during training

## [1.1.0] - 2025-01-17

### Added

- **Separate Storage Connection**: Store LaraDBChat's internal data (embeddings, query logs) in a separate database
  - New `storage.connection` config option to specify a different database connection
  - Built-in SQLite support with auto-creation (`storage.sqlite_path`)
  - New `php artisan laradbchat:migrate` command for connection-aware migrations
  - Interactive storage setup during `php artisan laradbchat:install`

- **Table Include/Exclude Configuration**: Enhanced control over which tables are trained
  - New `table_mode` config option: `'all'`, `'exclude'`, or `'include'`
  - New `include_tables` config option for whitelist mode
  - CLI flags for runtime filtering:
    - `--only=users,orders` - Train only specified tables
    - `--except=logs,cache` - Exclude specific tables
    - `--preview` - Preview tables without training
    - `--select` - Interactive table selection
  - Interactive table selection during `php artisan laradbchat:install`

- **Web UI Floating Chatbox**: Alpine.js + CSS chat widget for web pages
  - Floating toggle button with customizable position
  - Real-time chat interface with loading indicators
  - SQL query display (optional)
  - Data table formatting with pagination
  - LocalStorage chat history persistence
  - Fully customizable theming via config
  - Blade component: `<x-laradbchat-widget />`
  - Blade directive: `@laradbchat`

### Changed

- Improved PHP timeout handling for LLM requests in API endpoints
- Updated service provider with dynamic SQLite connection registration

### Fixed

- API route prefix handling for widget integration

## [1.0.0] - 2025-01-15

### Added

- Initial release
- Natural language to SQL conversion
- Support for Ollama, OpenAI, and Claude LLM providers
- Database schema extraction and training
- Embedding-based context retrieval
- Query execution with safety features (read-only mode, result limits)
- File and database query logging
- REST API endpoints
- Artisan commands (`laradbchat:train`, `laradbchat:ask`, `laradbchat:add-docs`, `laradbchat:install`)
- Laravel Facade support
- Deep analysis mode for Laravel models and migrations
