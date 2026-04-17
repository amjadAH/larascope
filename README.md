# LaraScope

A Laravel package that logs HTTP requests, duration, status, SQL queries, and memory usage — with a built-in web dashboard to browse and inspect them.

## Features

- **Zero-config setup** — auto-discovered and auto-registered into the `web` and `api` middleware groups
- **Request metadata** — method, URL, path, named route, IP address, authenticated user ID, status code
- **Performance data** — request duration (ms) and peak memory usage (MB)
- **SQL query logging** — captures all queries with bindings and execution time; automatically flags slow queries
- **Sensitive header redaction** — strips `Authorization`, `Cookie`, and `X-CSRF-Token` headers before persisting
- **Privacy-first** — request and response bodies are off by default
- **Built-in dashboard** — paginated, filterable log browser at `/larascope`
- **Log pruning** — `php artisan larascope:prune` removes logs older than a configurable retention period
- **Database fallback** — if a DB insert fails, the payload is written to the Laravel log so nothing is silently lost
- **Octane-safe** — per-request state is reset on every `handle()` call to prevent bleed between requests

## Requirements

| Dependency | Version |
|---|---|
| PHP | `^8.1` |
| Laravel | `^10.0 \| ^11.0 \| ^12.0 \| ^13.0` |

## Installation

Install the package via Composer:

```bash
composer require amjad-ah/larascope
```

The package is auto-discovered — no manual provider registration needed.

Run the migration to create the `larascope_request_logs` table:

```bash
php artisan migrate
```

That's it. LaraScope is now logging every HTTP request in the `web` and `api` middleware groups.

## Dashboard

Open `/larascope` in your browser to browse captured logs.

The list view supports filtering by **HTTP method**, **status code**, and **path substring**. The detail view shows the full SQL query log (with bindings and slow-query flags), request headers, memory peak, and duration.

Tailwind CSS is loaded via CDN — no asset pipeline required.

### Protecting the dashboard in production

Add Laravel's `auth` middleware (or any middleware you prefer) to the dashboard:

```php
// config/larascope.php
'dashboard' => [
    'middleware' => ['web', 'auth'],
],
```

## Configuration

Publish the config file to customise any option:

```bash
php artisan vendor:publish --tag=larascope-config
```

### Environment variables

| Variable | Default | Description |
|---|---|---|
| `LARASCOPE_ENABLED` | `true` | Master switch — disables all logging and middleware registration when `false` |
| `LARASCOPE_DB_CONNECTION` | `null` (Laravel default) | Database connection to use for storing logs |
| `LARASCOPE_DB_TABLE` | `larascope_request_logs` | Table name for log storage |
| `LARASCOPE_DASHBOARD_ENABLED` | `true` | Enable or disable the web dashboard |
| `LARASCOPE_DASHBOARD_PATH` | `larascope` | URL path for the dashboard |

### Full config reference

```php
// config/larascope.php

return [

    // Master switch — set to false to disable everything
    'enabled' => env('LARASCOPE_ENABLED', true),

    'database' => [
        'connection' => env('LARASCOPE_DB_CONNECTION', null),
        'table'      => env('LARASCOPE_DB_TABLE', 'larascope_request_logs'),
    ],

    // Middleware groups to auto-register the logging middleware into
    'middleware_groups' => ['web', 'api'],

    'dashboard' => [
        'enabled'    => env('LARASCOPE_DASHBOARD_ENABLED', true),
        'path'       => env('LARASCOPE_DASHBOARD_PATH', 'larascope'),
        'middleware' => ['web'],   // add 'auth' to restrict access
        'per_page'   => 25,
    ],

    'logging' => [
        'include_request_headers' => true,
        'include_request_body'    => false,  // off by default for privacy
        'include_response_body'   => false,  // off by default for privacy

        // Headers stripped before storing (case-insensitive)
        'exclude_headers' => [
            'authorization',
            'cookie',
            'x-csrf-token',
        ],

        // Paths to skip — supports Str::is() wildcards e.g. '_debugbar/*'
        'exclude_paths' => [],

        // HTTP methods to skip entirely e.g. ['OPTIONS']
        'exclude_methods' => [],
    ],

    'queries' => [
        'enabled'           => true,
        'slow_threshold_ms' => 100,  // queries >= this value are flagged as slow
    ],

    'pruning' => [
        'enabled'     => true,
        'retain_days' => 30,
    ],

];
```

## Excluding paths and methods

Skip specific routes using wildcards (powered by `Str::is()`):

```php
'exclude_paths' => [
    'health',
    '_debugbar/*',
    'telescope/*',
    'horizon/*',
],

'exclude_methods' => ['OPTIONS'],
```

The dashboard's own routes are always excluded automatically to prevent recursive log growth.

## Captured data

Each log entry stores 15 fields:

| Field | Type | Description |
|---|---|---|
| `method` | `string` | HTTP verb (`GET`, `POST`, …) |
| `url` | `string` | Full request URL including query string |
| `path` | `string` | URL path segment only |
| `route_name` | `string\|null` | Named route, if resolved |
| `ip_address` | `string\|null` | Client IP address |
| `user_id` | `int\|null` | Authenticated user ID (`Auth::id()`) |
| `status_code` | `int` | HTTP response status code |
| `duration_ms` | `float` | Request duration in milliseconds |
| `memory_peak_mb` | `float` | Peak memory usage in megabytes |
| `query_count` | `int` | Number of SQL queries executed |
| `queries` | `json` | Array of queries with `sql`, `bindings`, `time_ms`, and `slow` flag |
| `request_headers` | `json\|null` | Sanitised request headers |
| `request_body` | `json\|null` | Request input (opt-in) |
| `response_body` | `string\|null` | Response content (opt-in) |
| `created_at` | `timestamp` | When the log entry was created |

## Artisan commands

### Prune old logs

```bash
php artisan larascope:prune
```

Deletes all log entries older than `pruning.retain_days` (default: 30 days). Schedule this command to keep your table from growing unbounded:

```php
// routes/console.php (Laravel 11+)
Schedule::command('larascope:prune')->daily();
```

## Publishing assets

```bash
# Publish config
php artisan vendor:publish --tag=larascope-config

# Publish migration (to customise the table schema)
php artisan vendor:publish --tag=larascope-migrations

# Publish Blade views (to customise the dashboard UI)
php artisan vendor:publish --tag=larascope-views
```

## Architecture

```
HTTP Request
  → LaraScopeMiddleware   (resets state, captures start time, registers DB::listen)
    → RequestLogger       (builds structured 15-field payload)
      → DatabaseDriver    (persists to DB; falls back to Laravel log on failure)
        → RequestLog      (Eloquent model consumed by the dashboard)
          → DashboardController / PruneLogsCommand
```

Both `LaraScopeMiddleware` and `RequestLogger` are bound as **singletons** so the same instance handles both `handle()` and `terminate()`. Per-request state (`$collectedQueries`, `$shouldSkip`) is reset at the top of every `handle()` call, making the package safe under persistent runtimes like Laravel Octane.
