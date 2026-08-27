<?php

namespace Amjad\LaraScope\Tests\Unit;

use Amjad\LaraScope\Http\Filters\RequestLogFilters;
use Amjad\LaraScope\Models\RequestLog;
use Amjad\LaraScope\Tests\TestCase;
use Illuminate\Http\Request;

class RequestLogFiltersTest extends TestCase
{
    public function test_no_filters_returns_every_log(): void
    {
        $this->makeLog(['path' => 'api/users']);
        $this->makeLog(['path' => 'api/posts']);

        $this->assertEqualsCanonicalizing(['api/users', 'api/posts'], $this->filteredPaths([]));
    }

    public function test_blank_filter_values_are_ignored(): void
    {
        $this->makeLog(['path' => 'api/users']);
        $this->makeLog(['path' => 'api/posts']);

        $filteredPaths = $this->filteredPaths([
            'method' => '',
            'status' => '',
            'path'   => '',
        ]);

        $this->assertCount(2, $filteredPaths);
    }

    public function test_filters_by_a_single_method(): void
    {
        $this->makeLog(['method' => 'GET', 'path' => 'api/users']);
        $this->makeLog(['method' => 'POST', 'path' => 'api/posts']);

        $this->assertSame(['api/posts'], $this->filteredPaths(['method' => 'POST']));
    }

    public function test_filters_by_multiple_methods(): void
    {
        $this->makeLog(['method' => 'GET', 'path' => 'api/users']);
        $this->makeLog(['method' => 'POST', 'path' => 'api/posts']);
        $this->makeLog(['method' => 'DELETE', 'path' => 'api/items']);

        $filteredPaths = $this->filteredPaths(['method' => ['POST', 'DELETE']]);

        $this->assertEqualsCanonicalizing(['api/posts', 'api/items'], $filteredPaths);
    }

    public function test_method_filter_is_case_insensitive(): void
    {
        $this->makeLog(['method' => 'POST', 'path' => 'api/posts']);

        $this->assertSame(['api/posts'], $this->filteredPaths(['method' => 'post']));
    }

    public function test_filters_by_exact_status_code(): void
    {
        $this->makeLog(['status_code' => 200, 'path' => 'api/ok']);
        $this->makeLog(['status_code' => 404, 'path' => 'api/missing']);

        $this->assertSame(['api/missing'], $this->filteredPaths(['status' => '404']));
    }

    public function test_filters_by_status_class(): void
    {
        $this->makeLog(['status_code' => 200, 'path' => 'api/ok']);
        $this->makeLog(['status_code' => 404, 'path' => 'api/missing']);
        $this->makeLog(['status_code' => 422, 'path' => 'api/invalid']);
        $this->makeLog(['status_code' => 500, 'path' => 'api/boom']);

        $filteredPaths = $this->filteredPaths(['status_class' => '4xx']);

        $this->assertEqualsCanonicalizing(['api/missing', 'api/invalid'], $filteredPaths);
    }

    public function test_unknown_status_class_is_ignored(): void
    {
        $this->makeLog(['status_code' => 200, 'path' => 'api/ok']);

        $this->assertSame(['api/ok'], $this->filteredPaths(['status_class' => 'nonsense']));
    }

    public function test_filters_by_path_substring(): void
    {
        $this->makeLog(['path' => 'api/users']);
        $this->makeLog(['path' => 'api/orders']);

        $this->assertSame(['api/users'], $this->filteredPaths(['path' => 'user']));
    }

    public function test_filters_by_route_name_substring(): void
    {
        $this->makeLog(['path' => 'api/users', 'route_name' => 'users.index']);
        $this->makeLog(['path' => 'api/orders', 'route_name' => 'orders.index']);

        $this->assertSame(['api/users'], $this->filteredPaths(['route' => 'users.']));
    }

    public function test_filters_by_ip_address_substring(): void
    {
        $this->makeLog(['path' => 'api/users', 'ip_address' => '192.168.1.20']);
        $this->makeLog(['path' => 'api/orders', 'ip_address' => '10.0.0.5']);

        $this->assertSame(['api/users'], $this->filteredPaths(['ip' => '192.168.']));
    }

    public function test_filters_by_user_id(): void
    {
        $this->makeLog(['path' => 'api/users', 'user_id' => 7]);
        $this->makeLog(['path' => 'api/orders', 'user_id' => 9]);

        $this->assertSame(['api/users'], $this->filteredPaths(['user_id' => '7']));
    }

    public function test_filters_by_minimum_duration(): void
    {
        $this->makeLog(['path' => 'api/fast', 'duration_ms' => 20.0]);
        $this->makeLog(['path' => 'api/slow', 'duration_ms' => 850.0]);

        $this->assertSame(['api/slow'], $this->filteredPaths(['min_duration' => '500']));
    }

    public function test_filters_by_minimum_memory(): void
    {
        $this->makeLog(['path' => 'api/light', 'memory_peak_mb' => 4.0]);
        $this->makeLog(['path' => 'api/heavy', 'memory_peak_mb' => 64.0]);

        $this->assertSame(['api/heavy'], $this->filteredPaths(['min_memory' => '32']));
    }

    public function test_filters_by_minimum_query_count(): void
    {
        $this->makeLog(['path' => 'api/simple', 'query_count' => 2]);
        $this->makeLog(['path' => 'api/nplusone', 'query_count' => 140]);

        $this->assertSame(['api/nplusone'], $this->filteredPaths(['min_queries' => '50']));
    }

    public function test_filters_by_slow_query_flag(): void
    {
        $this->makeLog(['path' => 'api/fine', 'has_slow_queries' => false]);
        $this->makeLog(['path' => 'api/sluggish', 'has_slow_queries' => true]);

        $this->assertSame(['api/sluggish'], $this->filteredPaths(['slow' => '1']));
    }

    public function test_slow_filter_is_ignored_when_not_enabled(): void
    {
        $this->makeLog(['path' => 'api/fine', 'has_slow_queries' => false]);
        $this->makeLog(['path' => 'api/sluggish', 'has_slow_queries' => true]);

        $this->assertCount(2, $this->filteredPaths(['slow' => '0']));
    }

    public function test_filters_by_from_and_to_timestamps(): void
    {
        $this->makeLog(['path' => 'api/old', 'created_at' => '2026-08-01 10:00:00']);
        $this->makeLog(['path' => 'api/recent', 'created_at' => '2026-08-20 10:00:00']);
        $this->makeLog(['path' => 'api/newest', 'created_at' => '2026-08-25 10:00:00']);

        $filteredPaths = $this->filteredPaths([
            'from' => '2026-08-15 00:00:00',
            'to'   => '2026-08-21 00:00:00',
        ]);

        $this->assertSame(['api/recent'], $filteredPaths);
    }

    public function test_malformed_dates_are_ignored(): void
    {
        $this->makeLog(['path' => 'api/users']);

        $this->assertSame(['api/users'], $this->filteredPaths(['from' => 'not-a-date']));
    }

    public function test_range_preset_limits_results_to_the_recent_window(): void
    {
        $this->makeLog(['path' => 'api/stale', 'created_at' => now()->subDays(3)->toDateTimeString()]);
        $this->makeLog(['path' => 'api/fresh', 'created_at' => now()->subMinutes(10)->toDateTimeString()]);

        $this->assertSame(['api/fresh'], $this->filteredPaths(['range' => '1h']));
    }

    public function test_range_preset_is_ignored_when_an_explicit_from_is_given(): void
    {
        $this->makeLog(['path' => 'api/stale', 'created_at' => now()->subDays(3)->toDateTimeString()]);
        $this->makeLog(['path' => 'api/fresh', 'created_at' => now()->subMinutes(10)->toDateTimeString()]);

        $filteredPaths = $this->filteredPaths([
            'range' => '1h',
            'from'  => now()->subDays(7)->toDateTimeString(),
        ]);

        $this->assertCount(2, $filteredPaths);
    }

    public function test_unknown_range_preset_is_ignored(): void
    {
        $this->makeLog(['path' => 'api/stale', 'created_at' => now()->subDays(3)->toDateTimeString()]);

        $this->assertSame(['api/stale'], $this->filteredPaths(['range' => '99y']));
    }

    public function test_filters_combine_with_and_semantics(): void
    {
        $this->makeLog(['path' => 'api/users', 'method' => 'GET', 'status_code' => 500]);
        $this->makeLog(['path' => 'api/users/1', 'method' => 'GET', 'status_code' => 200]);
        $this->makeLog(['path' => 'api/orders', 'method' => 'POST', 'status_code' => 500]);

        $filteredPaths = $this->filteredPaths([
            'method'       => 'GET',
            'status_class' => '5xx',
            'path'         => 'users',
        ]);

        $this->assertSame(['api/users'], $filteredPaths);
    }

    public function test_default_sort_is_most_recent_first(): void
    {
        $this->makeLog(['path' => 'api/older', 'created_at' => '2026-08-01 10:00:00']);
        $this->makeLog(['path' => 'api/newer', 'created_at' => '2026-08-20 10:00:00']);

        $this->assertSame(['api/newer', 'api/older'], $this->sortedPaths([]));
    }

    public function test_sorts_by_slowest_request(): void
    {
        $this->makeLog(['path' => 'api/fast', 'duration_ms' => 10.0]);
        $this->makeLog(['path' => 'api/slow', 'duration_ms' => 990.0]);
        $this->makeLog(['path' => 'api/medium', 'duration_ms' => 300.0]);

        $this->assertSame(['api/slow', 'api/medium', 'api/fast'], $this->sortedPaths(['sort' => 'slowest']));
    }

    public function test_sorts_by_memory_usage(): void
    {
        $this->makeLog(['path' => 'api/light', 'memory_peak_mb' => 2.0]);
        $this->makeLog(['path' => 'api/heavy', 'memory_peak_mb' => 90.0]);

        $this->assertSame(['api/heavy', 'api/light'], $this->sortedPaths(['sort' => 'memory']));
    }

    public function test_sorts_by_query_count(): void
    {
        $this->makeLog(['path' => 'api/simple', 'query_count' => 1]);
        $this->makeLog(['path' => 'api/nplusone', 'query_count' => 120]);

        $this->assertSame(['api/nplusone', 'api/simple'], $this->sortedPaths(['sort' => 'queries']));
    }

    public function test_unknown_sort_falls_back_to_most_recent(): void
    {
        $this->makeLog(['path' => 'api/older', 'created_at' => '2026-08-01 10:00:00']);
        $this->makeLog(['path' => 'api/newer', 'created_at' => '2026-08-20 10:00:00']);

        $this->assertSame(['api/newer', 'api/older'], $this->sortedPaths(['sort' => 'drop table']));
    }

    public function test_is_active_is_false_when_no_filters_are_applied(): void
    {
        $this->assertFalse($this->filtersFor([])->isActive());
    }

    public function test_is_active_is_true_when_a_filter_is_applied(): void
    {
        $this->assertTrue($this->filtersFor(['path' => 'users'])->isActive());
    }

    public function test_sort_alone_does_not_count_as_an_active_filter(): void
    {
        $this->assertFalse($this->filtersFor(['sort' => 'slowest'])->isActive());
    }

    public function test_has_advanced_filters_is_false_when_only_primary_filters_are_applied(): void
    {
        $filters = $this->filtersFor(['path' => 'users', 'method' => 'GET', 'status_class' => '5xx', 'range' => '1h']);

        $this->assertFalse($filters->hasAdvancedFilters());
    }

    public function test_has_advanced_filters_is_true_when_an_advanced_filter_is_applied(): void
    {
        $this->assertTrue($this->filtersFor(['min_duration' => '500'])->hasAdvancedFilters());
    }

    public function test_active_chips_describe_each_applied_filter(): void
    {
        $chips = $this->filtersFor(['path' => 'users', 'min_duration' => '500'])->activeChips();

        $this->assertEqualsCanonicalizing(
            ['Path', 'Min duration'],
            array_column($chips, 'label')
        );
        $this->assertContains('users', array_column($chips, 'value'));
    }

    public function test_method_chip_lists_every_selected_method(): void
    {
        $chips = $this->filtersFor(['method' => ['GET', 'POST']])->activeChips();

        $this->assertSame('Method', $chips[0]['label']);
        $this->assertSame('GET, POST', $chips[0]['value']);
    }

    public function test_slow_chip_appears_only_when_the_toggle_is_enabled(): void
    {
        $this->assertSame([], $this->filtersFor(['slow' => '0'])->activeChips());
        $this->assertSame('Slow queries', $this->filtersFor(['slow' => '1'])->activeChips()[0]['label']);
    }

    public function test_chip_url_drops_only_its_own_filter(): void
    {
        $chips = $this->filtersFor(['path' => 'users', 'status' => '500'])->activeChips();

        $pathChipUrl = collect($chips)->firstWhere('label', 'Path')['url'];

        $this->assertStringNotContainsString('path=', $pathChipUrl);
        $this->assertStringContainsString('status=500', $pathChipUrl);
    }

    public function test_chip_url_resets_pagination(): void
    {
        $chips = $this->filtersFor(['path' => 'users', 'status' => '500', 'page' => '4'])->activeChips();

        $this->assertStringNotContainsString('page=', $chips[0]['url']);
    }

    public function test_chip_url_preserves_the_active_sort(): void
    {
        $chips = $this->filtersFor(['path' => 'users', 'sort' => 'slowest'])->activeChips();

        $this->assertStringContainsString('sort=slowest', $chips[0]['url']);
    }

    /**
     * Paths matching the given query parameters, filtering only.
     *
     * @param  array<string, mixed>  $queryParameters
     * @return array<int, string>
     */
    private function filteredPaths(array $queryParameters): array
    {
        return $this->filtersFor($queryParameters)
            ->apply(RequestLog::query())
            ->pluck('path')
            ->all();
    }

    /**
     * Paths matching the given query parameters, filtered and sorted.
     *
     * @param  array<string, mixed>  $queryParameters
     * @return array<int, string>
     */
    private function sortedPaths(array $queryParameters): array
    {
        $filters = $this->filtersFor($queryParameters);
        $query   = $filters->apply(RequestLog::query());

        return $filters->applySort($query)->pluck('path')->all();
    }

    /** @param array<string, mixed> $queryParameters */
    private function filtersFor(array $queryParameters): RequestLogFilters
    {
        return new RequestLogFilters(Request::create('/larascope', 'GET', $queryParameters));
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
