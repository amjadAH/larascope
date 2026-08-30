<?php

namespace Amjad\LaraScope\Tests\Unit;

use Amjad\LaraScope\Services\DatabaseDriver;
use Amjad\LaraScope\Services\RequestLogger;
use Amjad\LaraScope\Tests\TestCase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequestLoggerTest extends TestCase
{
    private RequestLogger $requestLogger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requestLogger = $this->app->make(RequestLogger::class);
    }

    public function test_build_payload_contains_all_required_fields(): void
    {
        $request   = Request::create('/api/users', 'GET');
        $response  = new Response('OK', 200);
        $startTime = microtime(true) - 0.1;

        $payload = $this->requestLogger->buildPayload($request, $response, [], $startTime);

        $this->assertArrayHasKey('method', $payload);
        $this->assertArrayHasKey('url', $payload);
        $this->assertArrayHasKey('path', $payload);
        $this->assertArrayHasKey('route_name', $payload);
        $this->assertArrayHasKey('ip_address', $payload);
        $this->assertArrayHasKey('user_id', $payload);
        $this->assertArrayHasKey('status_code', $payload);
        $this->assertArrayHasKey('duration_ms', $payload);
        $this->assertArrayHasKey('memory_peak_mb', $payload);
        $this->assertArrayHasKey('query_count', $payload);
        $this->assertArrayHasKey('queries', $payload);
        $this->assertArrayHasKey('has_slow_queries', $payload);
        $this->assertArrayHasKey('request_headers', $payload);
        $this->assertArrayHasKey('request_body', $payload);
        $this->assertArrayHasKey('response_body', $payload);
        $this->assertArrayHasKey('created_at', $payload);
    }

    public function test_build_payload_captures_method_and_status(): void
    {
        $request   = Request::create('/api/items', 'POST');
        $response  = new Response('Created', 201);
        $startTime = microtime(true);

        $payload = $this->requestLogger->buildPayload($request, $response, [], $startTime);

        $this->assertSame('POST', $payload['method']);
        $this->assertSame(201, $payload['status_code']);
        $this->assertSame('api/items', $payload['path']);
    }

    public function test_build_payload_duration_is_positive(): void
    {
        $request   = Request::create('/test', 'GET');
        $response  = new Response('', 200);
        $startTime = microtime(true) - 0.5;

        $payload = $this->requestLogger->buildPayload($request, $response, [], $startTime);

        $this->assertGreaterThan(0, $payload['duration_ms']);
    }

    public function test_build_payload_query_count_matches_collected_queries(): void
    {
        $request   = Request::create('/test', 'GET');
        $response  = new Response('', 200);
        $startTime = microtime(true);

        $collectedQueries = [
            ['sql' => 'select * from users', 'bindings' => [], 'time_ms' => 5.0],
            ['sql' => 'select * from posts', 'bindings' => [], 'time_ms' => 8.0],
        ];

        $payload = $this->requestLogger->buildPayload($request, $response, $collectedQueries, $startTime);

        $this->assertSame(2, $payload['query_count']);
        $this->assertCount(2, $payload['queries']);
    }

    public function test_build_payload_marks_slow_queries(): void
    {
        $this->app['config']->set('larascope.queries.slow_threshold_ms', 100);

        $request   = Request::create('/test', 'GET');
        $response  = new Response('', 200);
        $startTime = microtime(true);

        $collectedQueries = [
            ['sql' => 'select 1', 'bindings' => [], 'time_ms' => 50.0],
            ['sql' => 'select * from big_table', 'bindings' => [], 'time_ms' => 200.0],
        ];

        $payload = $this->requestLogger->buildPayload($request, $response, $collectedQueries, $startTime);

        $this->assertFalse($payload['queries'][0]['slow']);
        $this->assertTrue($payload['queries'][1]['slow']);
    }

    public function test_build_payload_strips_excluded_headers(): void
    {
        $this->app['config']->set('larascope.logging.include_request_headers', true);
        $this->app['config']->set('larascope.logging.exclude_headers', ['authorization', 'cookie', 'x-csrf-token']);

        $request = Request::create('/test', 'GET', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer secret-token',
            'HTTP_COOKIE'        => 'session=abc',
            'HTTP_ACCEPT'        => 'application/json',
        ]);
        $response  = new Response('', 200);
        $startTime = microtime(true);

        $payload = $this->requestLogger->buildPayload($request, $response, [], $startTime);

        $this->assertArrayNotHasKey('authorization', $payload['request_headers']);
        $this->assertArrayNotHasKey('cookie', $payload['request_headers']);
        $this->assertArrayHasKey('accept', $payload['request_headers']);
    }

    public function test_build_payload_returns_null_for_request_headers_when_disabled(): void
    {
        $this->app['config']->set('larascope.logging.include_request_headers', false);

        $request   = Request::create('/test', 'GET');
        $response  = new Response('', 200);
        $startTime = microtime(true);

        $payload = $this->requestLogger->buildPayload($request, $response, [], $startTime);

        $this->assertNull($payload['request_headers']);
    }

    public function test_build_payload_returns_null_for_request_body_by_default(): void
    {
        $this->app['config']->set('larascope.logging.include_request_body', false);

        $request   = Request::create('/test', 'POST', ['key' => 'value']);
        $response  = new Response('', 200);
        $startTime = microtime(true);

        $payload = $this->requestLogger->buildPayload($request, $response, [], $startTime);

        $this->assertNull($payload['request_body']);
    }

    public function test_build_payload_includes_request_body_when_enabled(): void
    {
        $this->app['config']->set('larascope.logging.include_request_body', true);

        $request   = Request::create('/test', 'POST', ['name' => 'Amjad']);
        $response  = new Response('', 200);
        $startTime = microtime(true);

        $payload = $this->requestLogger->buildPayload($request, $response, [], $startTime);

        $this->assertSame(['name' => 'Amjad'], $payload['request_body']);
    }

    public function test_build_payload_returns_null_for_response_body_by_default(): void
    {
        $this->app['config']->set('larascope.logging.include_response_body', false);

        $request   = Request::create('/test', 'GET');
        $response  = new Response('{"ok":true}', 200);
        $startTime = microtime(true);

        $payload = $this->requestLogger->buildPayload($request, $response, [], $startTime);

        $this->assertNull($payload['response_body']);
    }

    public function test_build_payload_includes_response_body_when_enabled(): void
    {
        $this->app['config']->set('larascope.logging.include_response_body', true);

        $request   = Request::create('/test', 'GET');
        $response  = new Response('{"ok":true}', 200, ['Content-Type' => 'application/json']);
        $startTime = microtime(true);

        $payload = $this->requestLogger->buildPayload($request, $response, [], $startTime);

        $this->assertSame([
            'content-type' => 'application/json',
            'content'      => '{"ok":true}',
        ], $payload['response_body']);
    }

    public function test_build_payload_response_body_content_type_is_null_when_header_absent(): void
    {
        $this->app['config']->set('larascope.logging.include_response_body', true);

        $request   = Request::create('/test', 'GET');
        $response  = new Response('plain text body', 200);
        $startTime = microtime(true);

        $payload = $this->requestLogger->buildPayload($request, $response, [], $startTime);

        $this->assertSame([
            'content-type' => null,
            'content'      => 'plain text body',
        ], $payload['response_body']);
    }

    public function test_build_payload_flags_has_slow_queries_when_a_query_exceeds_the_threshold(): void
    {
        $this->app['config']->set('larascope.queries.slow_threshold_ms', 100);

        $request   = Request::create('/test', 'GET');
        $response  = new Response('', 200);
        $startTime = microtime(true);

        $collectedQueries = [
            ['sql' => 'select 1', 'bindings' => [], 'time_ms' => 50.0],
            ['sql' => 'select * from big_table', 'bindings' => [], 'time_ms' => 200.0],
        ];

        $payload = $this->requestLogger->buildPayload($request, $response, $collectedQueries, $startTime);

        $this->assertTrue($payload['has_slow_queries']);
    }

    public function test_build_payload_has_slow_queries_is_false_when_every_query_is_fast(): void
    {
        $this->app['config']->set('larascope.queries.slow_threshold_ms', 100);

        $request   = Request::create('/test', 'GET');
        $response  = new Response('', 200);
        $startTime = microtime(true);

        $collectedQueries = [
            ['sql' => 'select 1', 'bindings' => [], 'time_ms' => 5.0],
            ['sql' => 'select 2', 'bindings' => [], 'time_ms' => 12.0],
        ];

        $payload = $this->requestLogger->buildPayload($request, $response, $collectedQueries, $startTime);

        $this->assertFalse($payload['has_slow_queries']);
    }

    public function test_build_payload_has_slow_queries_is_false_when_no_queries_ran(): void
    {
        $request   = Request::create('/test', 'GET');
        $response  = new Response('', 200);
        $startTime = microtime(true);

        $payload = $this->requestLogger->buildPayload($request, $response, [], $startTime);

        $this->assertFalse($payload['has_slow_queries']);
    }

    public function test_store_delegates_to_driver(): void
    {
        $driverMock = $this->createMock(DatabaseDriver::class);
        $driverMock->expects($this->once())
            ->method('log')
            ->with($this->arrayHasKey('method'));

        $logger = new RequestLogger($driverMock);

        $logger->store(['method' => 'GET', 'url' => 'http://localhost/test']);
    }
}
