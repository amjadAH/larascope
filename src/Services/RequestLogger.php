<?php

namespace Amjad\LaraScope\Services;

use Amjad\LaraScope\Services\DatabaseDriver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequestLogger
{
    public function __construct(private readonly DatabaseDriver $driver) {}

    /**
     * Build the structured payload array from the request/response data.
     * Array values for JSON columns are kept as PHP arrays here; each
     * driver is responsible for encoding them as needed.
     *
     * @param  array<int, array{sql: string, bindings: array<mixed>, time_ms: float}>  $collectedQueries
     * @return array<string, mixed>
     */
    public function buildPayload(
        Request $request,
        Response $response,
        array $collectedQueries,
        float $startTime
    ): array {
        $durationMs  = round((microtime(true) - $startTime) * 1000, 2);
        $memoryPeakMb = round(memory_get_peak_usage(true) / 1024 / 1024, 4);

        $slowThresholdMs = config('larascope.queries.slow_threshold_ms', 100);

        $queriesWithSlowFlag = array_map(static function (array $query) use ($slowThresholdMs): array {
            $query['slow'] = $query['time_ms'] >= $slowThresholdMs;
            return $query;
        }, $collectedQueries);

        $hasSlowQueries = collect($queriesWithSlowFlag)->contains('slow', true);

        return [
            'method'          => $request->method(),
            'url'             => $request->fullUrl(),
            'path'            => $request->path(),
            'route_name'      => optional($request->route())->getName(),
            'ip_address'      => $request->ip(),
            'user_id'         => Auth::id(),
            'status_code'     => $response->getStatusCode(),
            'duration_ms'     => $durationMs,
            'memory_peak_mb'  => $memoryPeakMb,
            'query_count'     => count($queriesWithSlowFlag),
            'queries'         => $queriesWithSlowFlag,
            'has_slow_queries' => $hasSlowQueries,
            'request_headers' => $this->buildRequestHeaders($request),
            'request_body'    => $this->buildRequestBody($request),
            'response_body'   => $this->buildResponseBody($response),
            'created_at'      => now()->toDateTimeString(),
        ];
    }

    /**
     * Persist a pre-built payload via the configured driver.
     *
     * @param  array<string, mixed>  $payload
     */
    public function store(array $payload): void
    {
        $this->driver->log($payload);
    }

    /**
     * Build and immediately persist a log entry.
     *
     * @param  array<int, array{sql: string, bindings: array<mixed>, time_ms: float}>  $collectedQueries
     */
    public function record(
        Request $request,
        Response $response,
        array $collectedQueries,
        float $startTime
    ): void {
        $this->store($this->buildPayload($request, $response, $collectedQueries, $startTime));
    }

    /** @return array<string, mixed>|null */
    private function buildRequestHeaders(Request $request): ?array
    {
        if (!config('larascope.logging.include_request_headers', true)) {
            return null;
        }

        $excludedHeaders = array_map('strtolower', config('larascope.logging.exclude_headers', []));

        return collect($request->headers->all())
            ->filter(static fn ($value, string $key) => !in_array(strtolower($key), $excludedHeaders, true))
            ->map(static fn ($value) => count($value) === 1 ? $value[0] : $value)
            ->toArray();
    }

    /** @return array<string, mixed>|null */
    private function buildRequestBody(Request $request): ?array
    {
        if (!config('larascope.logging.include_request_body', false)) {
            return null;
        }

        return $request->all();
    }

    /** @return array{content-type: string|null, content: string}|null */
    private function buildResponseBody(Response $response): ?array
    {
        if (!config('larascope.logging.include_response_body', false)) {
            return null;
        }

        $content = $response->getContent();

        if ($content === false || $content === '') {
            return null;
        }

        return [
            'content-type' => $response->headers->get('Content-Type'),
            'content'      => $content,
        ];
    }
}
