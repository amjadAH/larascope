<?php

namespace Amjad\LaraScope\Tests\Unit;

use Amjad\LaraScope\Models\RequestLog;
use Amjad\LaraScope\Services\RequestLogStats;
use Amjad\LaraScope\Tests\TestCase;

class RequestLogStatsTest extends TestCase
{
    public function test_an_empty_result_set_reports_zeroes_without_dividing_by_zero(): void
    {
        $stats = RequestLogStats::forQuery(RequestLog::query());

        $this->assertSame(0, $stats->total);
        $this->assertSame(0, $stats->errorCount);
        $this->assertSame(0, $stats->slowCount);
        $this->assertSame(0.0, $stats->averageDurationMs);
        $this->assertSame(0.0, $stats->maxDurationMs);
        $this->assertSame(0.0, $stats->errorRate());
    }

    public function test_counts_total_requests(): void
    {
        $this->makeLog();
        $this->makeLog();
        $this->makeLog();

        $this->assertSame(3, RequestLogStats::forQuery(RequestLog::query())->total);
    }

    public function test_counts_responses_at_or_above_400_as_errors(): void
    {
        $this->makeLog(['status_code' => 200]);
        $this->makeLog(['status_code' => 302]);
        $this->makeLog(['status_code' => 404]);
        $this->makeLog(['status_code' => 500]);

        $stats = RequestLogStats::forQuery(RequestLog::query());

        $this->assertSame(2, $stats->errorCount);
        $this->assertSame(50.0, $stats->errorRate());
    }

    public function test_reports_average_and_maximum_duration(): void
    {
        $this->makeLog(['duration_ms' => 100.0]);
        $this->makeLog(['duration_ms' => 300.0]);

        $stats = RequestLogStats::forQuery(RequestLog::query());

        $this->assertSame(200.0, $stats->averageDurationMs);
        $this->assertSame(300.0, $stats->maxDurationMs);
    }

    public function test_counts_requests_flagged_with_slow_queries(): void
    {
        $this->makeLog(['has_slow_queries' => true]);
        $this->makeLog(['has_slow_queries' => true]);
        $this->makeLog(['has_slow_queries' => false]);

        $this->assertSame(2, RequestLogStats::forQuery(RequestLog::query())->slowCount);
    }

    public function test_summarises_only_the_rows_matching_the_given_query(): void
    {
        $this->makeLog(['method' => 'GET', 'duration_ms' => 10.0, 'status_code' => 200]);
        $this->makeLog(['method' => 'POST', 'duration_ms' => 800.0, 'status_code' => 500]);

        $stats = RequestLogStats::forQuery(RequestLog::query()->where('method', 'POST'));

        $this->assertSame(1, $stats->total);
        $this->assertSame(1, $stats->errorCount);
        $this->assertSame(800.0, $stats->maxDurationMs);
    }

    public function test_error_rate_is_rounded_to_one_decimal_place(): void
    {
        $this->makeLog(['status_code' => 500]);
        $this->makeLog(['status_code' => 200]);
        $this->makeLog(['status_code' => 200]);

        $this->assertSame(33.3, RequestLogStats::forQuery(RequestLog::query())->errorRate());
    }

    /** @param array<string, mixed> $overrides */
    private function makeLog(array $overrides = []): RequestLog
    {
        return RequestLog::create(array_merge([
            'method'           => 'GET',
            'url'              => 'http://localhost/api/users',
            'path'             => 'api/users',
            'route_name'       => null,
            'ip_address'       => '127.0.0.1',
            'user_id'          => null,
            'status_code'      => 200,
            'duration_ms'      => 20.0,
            'memory_peak_mb'   => 6.0,
            'query_count'      => 0,
            'has_slow_queries' => false,
            'queries'          => null,
            'request_headers'  => null,
            'request_body'     => null,
            'response_body'    => null,
            'created_at'       => now()->toDateTimeString(),
        ], $overrides));
    }
}
