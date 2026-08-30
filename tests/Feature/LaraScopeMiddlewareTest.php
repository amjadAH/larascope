<?php

namespace Amjad\LaraScope\Tests\Feature;

use Amjad\LaraScope\Http\Middleware\LaraScopeMiddleware;
use Amjad\LaraScope\Services\RequestLogger;
use Amjad\LaraScope\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\MockInterface;
use Symfony\Component\HttpFoundation\Response;

class LaraScopeMiddlewareTest extends TestCase
{
    private function makeAndDispatch(
        LaraScopeMiddleware $middleware,
        Request $request,
        Response $response
    ): void {
        $returned = $middleware->handle($request, fn ($r) => $response);
        $middleware->terminate($request, $returned);
    }

    public function test_record_is_called_for_normal_route(): void
    {
        /** @var MockInterface $spy */
        $spy = Mockery::spy(RequestLogger::class);

        $middleware = new LaraScopeMiddleware($spy);

        $this->makeAndDispatch($middleware, Request::create('/api/users', 'GET'), new Response('ok', 200));

        $spy->shouldHaveReceived('record')->once();
    }

    public function test_record_is_not_called_when_larascope_is_disabled(): void
    {
        $this->app['config']->set('larascope.enabled', false);

        /** @var MockInterface $spy */
        $spy = Mockery::spy(RequestLogger::class);

        $middleware = new LaraScopeMiddleware($spy);

        $this->makeAndDispatch($middleware, Request::create('/api/users', 'GET'), new Response('ok', 200));

        $spy->shouldNotHaveReceived('record');
    }

    public function test_dashboard_route_is_not_recorded(): void
    {
        $dashboardPath = config('larascope.dashboard.path', 'larascope');

        /** @var MockInterface $spy */
        $spy = Mockery::spy(RequestLogger::class);

        $middleware = new LaraScopeMiddleware($spy);

        $this->makeAndDispatch($middleware, Request::create('/' . $dashboardPath, 'GET'), new Response('', 200));

        $spy->shouldNotHaveReceived('record');
    }

    public function test_dashboard_sub_route_is_not_recorded(): void
    {
        $dashboardPath = config('larascope.dashboard.path', 'larascope');

        /** @var MockInterface $spy */
        $spy = Mockery::spy(RequestLogger::class);

        $middleware = new LaraScopeMiddleware($spy);

        $this->makeAndDispatch($middleware, Request::create('/' . $dashboardPath . '/123', 'GET'), new Response('', 200));

        $spy->shouldNotHaveReceived('record');
    }

    public function test_excluded_path_is_not_recorded(): void
    {
        $this->app['config']->set('larascope.logging.exclude_paths', ['health']);

        /** @var MockInterface $spy */
        $spy = Mockery::spy(RequestLogger::class);

        $middleware = new LaraScopeMiddleware($spy);

        $this->makeAndDispatch($middleware, Request::create('/health', 'GET'), new Response('', 200));

        $spy->shouldNotHaveReceived('record');
    }

    public function test_excluded_path_wildcard_is_not_recorded(): void
    {
        $this->app['config']->set('larascope.logging.exclude_paths', ['telescope/*']);

        /** @var MockInterface $spy */
        $spy = Mockery::spy(RequestLogger::class);

        $middleware = new LaraScopeMiddleware($spy);

        $this->makeAndDispatch($middleware, Request::create('/telescope/requests', 'GET'), new Response('', 200));

        $spy->shouldNotHaveReceived('record');
    }

    public function test_excluded_method_is_not_recorded(): void
    {
        $this->app['config']->set('larascope.logging.exclude_methods', ['OPTIONS']);

        /** @var MockInterface $spy */
        $spy = Mockery::spy(RequestLogger::class);

        $middleware = new LaraScopeMiddleware($spy);

        $this->makeAndDispatch($middleware, Request::create('/api/resource', 'OPTIONS'), new Response('', 204));

        $spy->shouldNotHaveReceived('record');
    }

    public function test_exception_in_record_does_not_propagate(): void
    {
        $logger = Mockery::mock(RequestLogger::class);
        $logger->shouldReceive('record')->andThrow(new \RuntimeException('DB down'));

        $middleware = new LaraScopeMiddleware($logger);

        // terminate() must swallow the exception — it must not propagate.
        $this->makeAndDispatch($middleware, Request::create('/api/users', 'GET'), new Response('ok', 200));

        $this->assertTrue(true);
    }

    public function test_state_is_reset_between_requests(): void
    {
        // First request: LaraScope disabled — record() must NOT be called.
        $this->app['config']->set('larascope.enabled', false);

        /** @var MockInterface $spy */
        $spy        = Mockery::spy(RequestLogger::class);
        $middleware = new LaraScopeMiddleware($spy);

        $this->makeAndDispatch($middleware, Request::create('/first', 'GET'), new Response('ok', 200));

        // Re-enable and run a second request on the same middleware instance.
        $this->app['config']->set('larascope.enabled', true);

        $this->makeAndDispatch($middleware, Request::create('/second', 'GET'), new Response('ok', 200));

        // record() should be called exactly once (for the second request only).
        $spy->shouldHaveReceived('record')->once();
    }

    public function test_query_listener_does_not_accumulate_across_requests(): void
    {
        // Regression test: the middleware is bound as a singleton (needed so
        // terminate() sees the same instance as handle()), and under Octane
        // that same instance handles every request on the worker. If
        // DB::listen() were registered inside handle(), each request would
        // stack another listener onto the shared event dispatcher, so a
        // single query on request N would be captured N times.
        $capturedQueries = [];

        /** @var MockInterface $logger */
        $logger = Mockery::mock(RequestLogger::class);
        $logger->shouldReceive('record')
            ->andReturnUsing(function (Request $_request, Response $_response, array $collectedQueries) use (&$capturedQueries): void {
                $capturedQueries[] = $collectedQueries;
            });

        $middleware = new LaraScopeMiddleware($logger);

        $runOneQueryRequest = function (LaraScopeMiddleware $middleware, string $path): void {
            $request  = Request::create($path, 'GET');
            $response = $middleware->handle($request, function () {
                DB::select('select 1');

                return new Response('ok', 200);
            });
            $middleware->terminate($request, $response);
        };

        $runOneQueryRequest($middleware, '/first');
        $runOneQueryRequest($middleware, '/second');

        // Each request ran exactly one query — it must be captured exactly
        // once per request, not once per listener accumulated so far.
        $this->assertCount(1, $capturedQueries[0]);
        $this->assertCount(1, $capturedQueries[1]);
    }
}
