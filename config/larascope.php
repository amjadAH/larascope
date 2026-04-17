<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enable LaraScope
    |--------------------------------------------------------------------------
    | When disabled, no requests are logged and no middleware is registered.
    */
    'enabled' => env('LARASCOPE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    | Options used by the "database" storage driver.
    |
    | connection  — which DB connection to use (null = Laravel default).
    | table       — the table name where logs are stored.
    |
    | These settings are also respected by the dashboard Eloquent model and
    | the migration, so you only need to change them in one place.
    */
    'database' => [
        'connection' => env('LARASCOPE_DB_CONNECTION', null),
        'table'      => env('LARASCOPE_DB_TABLE', 'larascope_request_logs'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    | The read-only web UI for browsing request logs. Add 'auth' to the
    | middleware array to restrict access in production.
    */
    // HTTP middleware groups to automatically push LaraScopeMiddleware into.
    // Remove a group or set to an empty array to disable global auto-registration.
    'middleware_groups' => ['web', 'api'],

    'dashboard' => [
        'enabled'    => env('LARASCOPE_DASHBOARD_ENABLED', true),
        'path'       => env('LARASCOPE_DASHBOARD_PATH', 'larascope'),
        'middleware' => ['web'],
        'per_page'   => 25,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Options
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'include_request_headers' => true,
        'include_request_body'    => false,
        'include_response_body'   => false,

        // Headers to strip before storing (case-insensitive).
        'exclude_headers' => [
            'authorization',
            'cookie',
            'x-csrf-token',
        ],

        // Paths to skip entirely. Supports wildcards via Str::is() rules.
        // e.g. 'health', '_debugbar/*', 'telescope/*'
        'exclude_paths' => [],

        // HTTP methods to skip entirely (uppercase).
        // e.g. 'OPTIONS'
        'exclude_methods' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | SQL Query Logging
    |--------------------------------------------------------------------------
    */
    'queries' => [
        'enabled'          => true,
        'slow_threshold_ms' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Pruning
    |--------------------------------------------------------------------------
    | Run `php artisan larascope:prune` (or schedule it) to remove old logs.
    */
    'pruning' => [
        'enabled'     => true,
        'retain_days' => 30,
    ],

];
