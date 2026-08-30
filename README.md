# LaraScope

A Laravel package that logs HTTP requests, duration, status, SQL queries, and memory usage — with a built-in web dashboard to browse and inspect them.

## Features

- **Zero-config setup** — auto-discovered and auto-registered into the `web` and `api` middleware groups
- **Request metadata** — method, URL, path, named route, IP address, authenticated user ID, status code
- **Performance data** — request duration (ms) and peak memory usage (MB)
- **SQL query logging** — captures all queries with bindings and execution time; automatically flags slow queries
- **Sensitive header redaction** — strips `Authorization`, `Cookie`, and `X-CSRF-Token` headers before persisting
- **Privacy-first** — request and response bodies are off by default
- **Built-in dashboard** — paginated log browser at `/larascope` with advanced filters, sorting, a summary strip and dark mode
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
composer require amjdhsan/larascope
```

The package is auto-discovered — no manual provider registration needed.

> **Coming from `amjad-ah/larascope`?** That package is abandoned as of 2.0.0. The package name, the root namespace (`AmjadAH\LaraScope\` → `Amjad\LaraScope\`) and the table schema all changed — see [CHANGELOG.md](CHANGELOG.md) for the upgrade steps.

Run the migration to create the `larascope_request_logs` table:

```bash
php artisan migrate
```

That's it. LaraScope is now logging every HTTP request in the `web` and `api` middleware groups.

## Dashboard

Open `/larascope` in your browser to browse captured logs.

The list view opens with a summary strip — request count, error rate, average and slowest duration, and how many requests ran a slow query — computed over whatever the active filters match, so the figures always describe the rows below them.

Each row draws its duration as a bar scaled against the slowest request on the page, so an outlier is visible before you read a number. The bar turns amber when the request ran a slow query.

The detail view shows the full SQL query log (with bindings, per-query timing bars and slow-query flags), request headers, memory peak, and duration.

### Filters

Filters combine with AND, and every one is a plain query-string parameter — bookmark a filtered view or share the URL.

| Filter | Parameter | Notes |
|---|---|---|
| HTTP method | `method[]` | Multi-select; case-insensitive |
| Status class | `status_class` | `2xx`, `3xx`, `4xx`, `5xx` |
| Exact status | `status` | e.g. `404` |
| Path | `path` | Substring match |
| Route name | `route` | Substring match |
| IP address | `ip` | Substring match, so `10.0.` matches a subnet |
| User ID | `user_id` | Exact match |
| Time window | `from`, `to` | Any parseable datetime |
| Quick range | `range` | `15m`, `1h`, `24h`, `7d` — ignored when `from`/`to` are set |
| Min duration | `min_duration` | Milliseconds |
| Min memory | `min_memory` | Megabytes |
| Min queries | `min_queries` | Query count |
| Slow queries only | `slow=1` | Requests that ran at least one slow query |
| Sort | `sort` | `recent` (default), `slowest`, `memory`, `queries` |

Unknown, blank and malformed values are ignored rather than raising an error, so a hand-edited URL can never break the page. Active filters appear as chips above the table; each chip's × removes just that one filter.

Tailwind CSS is loaded via CDN — no asset pipeline required. The dashboard follows your OS light/dark preference and remembers the toggle in `localStorage`.

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

Each log entry stores 16 fields:

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
| `has_slow_queries` | `bool` | Whether any query crossed the slow threshold — indexed, so the dashboard can filter on it |
| `queries` | `json` | Array of queries with `sql`, `bindings`, `time_ms`, and `slow` flag |
| `request_headers` | `json\|null` | Sanitised request headers |
| `request_body` | `json\|null` | Request input (opt-in) |
| `response_body` | `json\|null` | Response content (opt-in), stored as `{"content-type": ..., "content": ...}` |
| `created_at` | `timestamp` | When the log entry was created |

### Indexes

The migration indexes `created_at`, `method`, `status_code`, `user_id`, `duration_ms` and `has_slow_queries` — the columns the dashboard filters and sorts on. The substring filters (`path`, `route_name`, `ip_address`) match with a leading wildcard and cannot use an index, so they are deliberately left unindexed.

> **Upgrading from 1.x?** The `has_slow_queries` column and these indexes are part of the original migration, so an install that already migrated will not pick them up. Until the column exists, every insert fails and lands in the application log via the fallback path. [CHANGELOG.md](CHANGELOG.md) has the migration to run.

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
    → RequestLogger       (builds structured 16-field payload)
      → DatabaseDriver    (persists to DB; falls back to Laravel log on failure)
        → RequestLog      (Eloquent model consumed by the dashboard)
          → DashboardController / PruneLogsCommand
```

Both `LaraScopeMiddleware` and `RequestLogger` are bound as **singletons** so the same instance handles both `handle()` and `terminate()`. Per-request state (`$collectedQueries`, `$shouldSkip`) is reset at the top of every `handle()` call, making the package safe under persistent runtimes like Laravel Octane.
