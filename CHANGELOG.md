# Changelog

All notable changes to this project are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.1.1] - 2026-08-30

### Fixed

- **`larascope:prune` now honours the configured connection and table.** The command
  queried `larascope_request_logs` on the default connection regardless of
  `larascope.database.connection` and `larascope.database.table`, which the writer, the
  model and the migration all already respect. On an install with a custom table name the
  prune matched nothing and reported `0` pruned rows; on one with a dedicated logging
  connection it ran against the wrong database. Either way retention was silently never
  applied and logs grew without bound.

## [2.1.0] - 2026-08-30

### Breaking

- **`response_body` is now stored as `{"content-type": ..., "content": ...}` instead of a raw
  string.** The column type (`json`), the model's `'array'` cast, and the payload builder
  previously disagreed on this field's shape: with `include_response_body` enabled (off by
  default), inserting a non-JSON body (e.g. HTML) failed on MySQL/PostgreSQL because the raw
  string isn't valid JSON, and on any backend where the insert did succeed, the `'array'` cast
  failed to decode it back and silently returned `null`. In practice nothing consuming this
  field could have worked before this release. Code reading `$requestLog->response_body`
  directly needs to change from treating it as a string to `$requestLog->response_body['content']`
  (and `['content-type']` for the response's captured `Content-Type` header).

### Fixed

- **Octane: query listener no longer accumulates across requests.** `DB::listen()` was
  registered inside `LaraScopeMiddleware::handle()`, which runs on every request. Since the
  middleware is bound as a singleton (required so `terminate()` sees the same instance as
  `handle()`), a persistent runtime like Octane reuses that instance for many requests, so a
  new listener closure was stacked onto the shared event dispatcher every time — worker
  memory grew unbounded, and each query got captured once per accumulated listener, inflating
  `query_count` and duplicating entries in `queries` the longer a worker stayed alive. The
  listener is now registered once, in the constructor, which runs exactly once per instance
  under both PHP-FPM (fresh instance per request) and Octane (one instance for many requests).

## [2.0.0] - 2026-08-27

### Breaking

- **The package has been renamed** from `amjad-ah/larascope` to `amjdhsan/larascope`.
  The old package is abandoned and will receive no further releases.
- **The root namespace has been renamed** from `AmjadAH\LaraScope\` to `Amjad\LaraScope\`.
  Update any direct imports — most commonly `Amjad\LaraScope\Models\RequestLog` and
  `Amjad\LaraScope\Http\Middleware\LaraScopeMiddleware`. Applications that only rely on
  auto-discovery and the `larascope` middleware alias need no change.
- **The log table gained a `has_slow_queries` column and six indexes.** These were added
  to the original migration, so an existing install will not pick them up by running
  `php artisan migrate`. Until the column exists, every insert fails and the payload is
  written to the application log via the fallback path. See *Upgrading* below.

### Added

- Advanced dashboard filtering: multi-select HTTP method, status class (`2xx`–`5xx`) as
  well as exact status, path / route name / IP substring, user ID, an explicit `from`–`to`
  window, quick ranges (15m, 1h, 24h, 7d), minimum duration, memory and query count, and a
  slow-queries-only toggle. Filters combine with AND and are plain query-string parameters,
  so a filtered view can be bookmarked or shared.
- Sorting by most recent, slowest, most memory or most queries.
- Summary strip on the list view — request count, error rate, average and slowest duration,
  and slow-query count — computed over the filtered set in a single aggregate query.
- Per-row duration bars on the list view and per-query timing bars on the detail view,
  scaled so an outlier is visible without reading the numbers.
- Dismissible chips for each active filter; each removes only its own parameter.
- Dark mode, following the OS preference and remembered in `localStorage`.
- `has_slow_queries` column, populated by `RequestLogger` and indexed, so the dashboard can
  filter on slow requests without a JSON lookup (`whereJsonContains` has no portable
  partial-object match across MySQL, PostgreSQL and SQLite).
- Indexes on `created_at`, `method`, `status_code`, `user_id`, `duration_ms` and
  `has_slow_queries`.

### Changed

- Dashboard views were restructured and restyled; the filter panel and summary strip are now
  partials under `resources/views/dashboard/partials/`. **If you published the views in 1.x**,
  your published copies still take precedence and will keep rendering the old UI. Re-publish
  with `--force` to pick up the new dashboard, or keep yours — the old views only read
  `$requestLogs` and remain compatible.
- Middleware registration now goes through the HTTP kernel where one is available, so a
  package that re-syncs kernel middleware groups (Laravel Sanctum does this) can no longer
  silently drop LaraScope's middleware.

### Upgrading from 1.x

1. Swap the dependency:

   ```bash
   composer remove amjad-ah/larascope
   composer require amjdhsan/larascope
   ```

2. Update any direct imports from `AmjadAH\LaraScope\...` to `Amjad\LaraScope\...`.

3. Add the new column and indexes. Because they are part of the original migration, an
   install that already migrated needs a small migration of its own:

   ```php
   Schema::table(config('larascope.database.table'), function (Blueprint $table) {
       $table->boolean('has_slow_queries')->default(false);

       $table->index('created_at');
       $table->index('method');
       $table->index('status_code');
       $table->index('user_id');
       $table->index('duration_ms');
       $table->index('has_slow_queries');
   });
   ```

4. Optional, but recommended — backfill the flag for rows logged under 1.x. Without it those
   rows read as "no slow queries" on the dashboard even when their stored query log says
   otherwise:

   ```sql
   UPDATE larascope_request_logs
   SET has_slow_queries = 1
   WHERE queries LIKE '%"slow":true%';
   ```

## [1.0.2] - Earlier releases

Released as `amjad-ah/larascope`. See the Git history for details.

[2.0.0]: https://github.com/amjdhsan/larascope/releases/tag/v2.0.0
