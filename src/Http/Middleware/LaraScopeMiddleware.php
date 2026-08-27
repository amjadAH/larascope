<?php

namespace Amjad\LaraScope\Http\Middleware;

use Amjad\LaraScope\Services\RequestLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LaraScopeMiddleware
{
    private float $startTime;

    /** @var array<int, array{sql: string, bindings: array<mixed>, time_ms: float}> */
    private array $collectedQueries = [];

    private bool $shouldSkip = false;

    public function __construct(private readonly RequestLogger $requestLogger) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Reset per-request state. Required when the middleware is a singleton
        // (e.g. with Octane) so state never bleeds between requests.
        $this->collectedQueries = [];
        $this->shouldSkip = false;

        if (!config('larascope.enabled', true) || $this->isExcluded($request)) {
            $this->shouldSkip = true;

            return $next($request);
        }

        // Capture start time here, not in the constructor, so it reflects
        // the actual moment this request began to be handled.
        $this->startTime = microtime(true);

        if (config('larascope.queries.enabled', true)) {
            // DB::listen registers a listener on Laravel's event dispatcher.
            // In PHP-FPM each request runs in a fresh process, so listeners
            // reset automatically. Note: long-running servers (Octane /
            // FrankenPHP) require additional Octane-specific handling.

            DB::listen(function (object $query): void {
                $this->collectedQueries[] = [
                    'sql'      => $query->sql,
                    'bindings' => $query->bindings,
                    'time_ms'  => $query->time,
                ];
            });
        }

        return $next($request);
    }

    /**
     * Called by Laravel after the response is dispatched.
     *
     * Note: on PHP-FPM, fastcgi_finish_request() closes the client connection
     * before this runs, but the FPM worker remains occupied. On the built-in
     * PHP server and Octane the server process is also blocked here.
     */
    public function terminate(Request $request, Response $response): void
    {
        if ($this->shouldSkip) {
            return;
        }

        try {
            $this->requestLogger->record(
                $request,
                $response,
                $this->collectedQueries,
                $this->startTime
            );
        } catch (Throwable) {
            // Logging must never break the application.
        }
    }

    private function isExcluded(Request $request): bool
    {
        // Always skip the dashboard's own routes to avoid infinite log growth.
        if (config('larascope.dashboard.enabled', true)) {
            $dashboardPath = config('larascope.dashboard.path', 'larascope');

            if ($request->is($dashboardPath) || $request->is($dashboardPath . '/*')) {
                return true;
            }
        }

        $excludedPaths = config('larascope.logging.exclude_paths', []);

        foreach ($excludedPaths as $excludedPath) {
            if ($request->is($excludedPath)) {
                return true;
            }
        }

        $excludedMethods = array_map('strtoupper', config('larascope.logging.exclude_methods', []));

        if (!empty($excludedMethods) && in_array($request->method(), $excludedMethods, true)) {
            return true;
        }

        return false;
    }
}
