<?php

namespace AmjadAH\LaraScope\Tests\Unit;

use AmjadAH\LaraScope\Models\RequestLog;
use AmjadAH\LaraScope\Tests\TestCase;

class RequestLogModelTest extends TestCase
{
    public function test_has_slow_queries_returns_false_when_queries_is_empty(): void
    {
        $requestLog          = new RequestLog();
        $requestLog->queries = [];

        $this->assertFalse($requestLog->hasSlowQueries());
    }

    public function test_has_slow_queries_returns_false_when_no_query_is_slow(): void
    {
        $requestLog          = new RequestLog();
        $requestLog->queries = [
            ['sql' => 'select 1', 'time_ms' => 10.0, 'slow' => false],
            ['sql' => 'select 2', 'time_ms' => 30.0, 'slow' => false],
        ];

        $this->assertFalse($requestLog->hasSlowQueries());
    }

    public function test_has_slow_queries_returns_true_when_at_least_one_query_is_slow(): void
    {
        $requestLog          = new RequestLog();
        $requestLog->queries = [
            ['sql' => 'select 1', 'time_ms' => 10.0, 'slow' => false],
            ['sql' => 'select * from big_table', 'time_ms' => 500.0, 'slow' => true],
        ];

        $this->assertTrue($requestLog->hasSlowQueries());
    }

    public function test_has_slow_queries_returns_false_when_queries_is_null(): void
    {
        $requestLog          = new RequestLog();
        $requestLog->queries = null;

        $this->assertFalse($requestLog->hasSlowQueries());
    }

    public function test_scope_with_queries_filters_out_requests_without_queries(): void
    {
        RequestLog::create($this->makePayload(['query_count' => 0]));
        RequestLog::create($this->makePayload(['query_count' => 3, 'method' => 'POST']));

        $results = RequestLog::withQueries()->get();

        $this->assertCount(1, $results);
        $this->assertSame('POST', $results->first()->method);
    }

    public function test_get_table_uses_configured_table_name(): void
    {
        $this->app['config']->set('larascope.database.table', 'custom_log_table');

        $requestLog = new RequestLog();

        $this->assertSame('custom_log_table', $requestLog->getTable());
    }

    public function test_json_columns_are_cast_to_arrays(): void
    {
        $queriesData = [['sql' => 'select 1', 'time_ms' => 1.0, 'slow' => false, 'bindings' => []]];

        // Pass PHP arrays directly — Eloquent's 'array' cast JSON-encodes them for storage.
        RequestLog::create($this->makePayload([
            'queries'         => $queriesData,
            'request_headers' => ['accept' => 'application/json'],
        ]));

        $log = RequestLog::first();

        $this->assertIsArray($log->queries);
        $this->assertIsArray($log->request_headers);
    }

    /** @param array<string, mixed> $overrides */
    private function makePayload(array $overrides = []): array
    {
        return array_merge([
            'method'          => 'GET',
            'url'             => 'http://localhost/test',
            'path'            => 'test',
            'route_name'      => null,
            'ip_address'      => '127.0.0.1',
            'user_id'         => null,
            'status_code'     => 200,
            'duration_ms'     => 10.0,
            'memory_peak_mb'  => 4.0,
            'query_count'     => 0,
            'queries'         => null,
            'request_headers' => null,
            'request_body'    => null,
            'response_body'   => null,
            'created_at'      => now()->toDateTimeString(),
        ], $overrides);
    }
}
