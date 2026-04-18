# LaraScope – Agent Instructions

LaraScope is a Laravel package for HTTP request logging and monitoring. It captures request/response metadata, SQL queries, memory usage, and surfaces them via a built-in dashboard UI.

## Architecture

```ini
HTTP Request
  → LaraScopeMiddleware (captures timing + SQL via DB::listen)
    → RequestLogger (builds structured payload)
      → DatabaseDriver (persists to DB, falls back to app log)
        → RequestLog (Eloquent model)
          → DashboardController / PruneLogsCommand (consumers)
```

Key source files:

| File | Role |
|------|------|
| [src/LaraScopeServiceProvider.php](src/LaraScopeServiceProvider.php) | Bootstraps package: config merge, singleton bindings, middleware registration, publishable assets |
| [src/Http/Middleware/LaraScopeMiddleware.php](src/Http/Middleware/LaraScopeMiddleware.php) | Captures `$startTime` and SQL queries; calls `RequestLogger` in `terminate()` |
| [src/Services/RequestLogger.php](src/Services/RequestLogger.php) | Transforms raw request/response into a structured 15-field payload |
| [src/Services/DatabaseDriver.php](src/Services/DatabaseDriver.php) | Persists payload to DB; on failure, falls back to the application log |
| [src/Models/RequestLog.php](src/Models/RequestLog.php) | Eloquent model; JSON casts, `hasSlowQueries()`, `withQueries()` scope |
| [src/Http/Controllers/DashboardController.php](src/Http/Controllers/DashboardController.php) | Dashboard list (filtered) and detail views |
| [src/Console/Commands/PruneLogsCommand.php](src/Console/Commands/PruneLogsCommand.php) | `php artisan larascope:prune` – deletes logs older than `pruning.retain_days` |
| [config/larascope.php](config/larascope.php) | Single source of truth for all configuration |
| [database/migrations/2026_04_17_000000_create_larascope_request_logs_table.php](database/migrations/2026_04_17_000000_create_larascope_request_logs_table.php) | Creates `larascope_request_logs` table |

## Key Conventions

### Singleton & Octane Safety

- Middleware and `RequestLogger` are bound as **singletons**
- `LaraScopeMiddleware::handle()` **must** reset per-request state (`$collectedQueries`, `$shouldSkip`) to prevent state leakage under Octane or other persistent runtimes

### Configuration

- All options read from `config/larascope.php`; the Eloquent model and migration both use the configured connection/table
- Sensitive headers (`authorization`, `cookie`, `x-csrf-token`) are stripped by default
- Request/response bodies are **off** by default for privacy

### Middleware Registration

- Automatically pushed into groups listed in `config('larascope.middleware_groups')` (default: `['web', 'api']`)
- Dashboard routes are auto-excluded to prevent recursive logging
- Path exclusions support wildcard patterns via `Str::is()`

### Error Handling

- `LaraScopeMiddleware` wraps the logging call in try/catch – logging failures must never break the request
- `DatabaseDriver` catches insert failures and writes the payload to the Laravel log as a fallback

## Artisan Commands

```bash
php artisan larascope:prune          # Delete logs older than pruning.retain_days (default: 30)

php artisan vendor:publish --tag=larascope-config        # Publish config
php artisan vendor:publish --tag=larascope-migrations    # Publish migration
php artisan vendor:publish --tag=larascope-views         # Publish Blade views
```

## Development Setup

**Requirements:** PHP ^8.1, Laravel ^10|^11|^12|^13
**Dev dependency:** `orchestra/testbench ^8.0|^9.0|^10.0`

There are no build scripts in `composer.json`. Install dependencies with:

```bash
composer install
```

## Dashboard

- Accessible at `/{larascope.dashboard.path}` (default: `/larascope`)
- Filtered by method, status code, and path substring
- Detail view shows SQL queries with bindings, headers, memory, and duration
- Tailwind CSS is loaded via CDN – no asset pipeline needed
