# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

LaraScope is a Laravel package (not an app) that logs HTTP requests, SQL queries, duration
and memory usage, and ships a built-in Blade dashboard to browse them. Published to
Packagist as `amjdhsan/larascope`.

**Read [AGENTS.md](AGENTS.md) before making non-trivial changes.** It documents the
request → middleware → logger → driver → model pipeline, Octane singleton-safety
requirements, filter/view conventions, and known Blade gotchas — do not duplicate that
content here; treat it as the primary architecture reference alongside this file.

## Commands

Install dependencies (there are no composer `scripts`; invoke vendor binaries directly):

```bash
composer install
```

Run the full test suite (Unit + Feature, defined in `phpunit.xml`, against an in-memory
SQLite connection):

```bash
vendor/bin/phpunit
```

Run a single test file or method:

```bash
vendor/bin/phpunit tests/Unit/RequestLoggerTest.php
vendor/bin/phpunit --filter test_it_captures_slow_queries
```

There is no lint/static-analysis tooling configured in this repo (no Pint, PHPStan, or CI
workflow present as of this writing).

### Previewing the dashboard in a browser

This is a package with no host app, so `php artisan serve` doesn't apply. Use Orchestra
Testbench's skeleton app instead:

```bash
vendor/bin/testbench package:create-sqlite-db
DB_CONNECTION=sqlite vendor/bin/testbench migrate --force
DB_CONNECTION=sqlite vendor/bin/testbench serve --port=8123
# → http://127.0.0.1:8123/larascope
```

Seed rows directly into `vendor/orchestra/testbench-core/laravel/database/database.sqlite`
via plain PDO to populate the dashboard — no Laravel bootstrapping needed for seeding.
Clean up with `vendor/bin/testbench package:drop-sqlite-db` and `package:purge-skeleton`;
otherwise the testbench skeleton keeps the seeded database around. `vendor/` is gitignored,
so none of this appears in `git status`.

## Conventions not covered in AGENTS.md

- Versioning follows [Keep a Changelog](https://keepachangelog.com/) + SemVer; every
  release gets an entry in `CHANGELOG.md` before/with the release commit, with enough
  detail to explain user-facing impact (see existing entries for the expected depth,
  especially how breaking changes and silent-failure bugs are called out).
- The package supports Laravel `^10.0 | ^11.0 | ^12.0 | ^13.0` on PHP `^8.1` — keep new code
  compatible across that whole range (e.g. avoid APIs only available in the newest Laravel
  version) unless a change is explicitly version-gated.
