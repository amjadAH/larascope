<?php

namespace Amjad\LaraScope\Services;

use Illuminate\Database\Eloquent\Builder;

/**
 * Aggregate figures for the dashboard's summary strip, computed over the
 * same filtered query that feeds the table so the numbers always describe
 * what the user is actually looking at.
 */
class RequestLogStats
{
    public function __construct(
        public readonly int $total,
        public readonly int $errorCount,
        public readonly int $slowCount,
        public readonly float $averageDurationMs,
        public readonly float $maxDurationMs,
    ) {}

    /**
     * Summarise a query in a single round trip.
     *
     * The CASE expressions are written without comparing the boolean column
     * to an integer so the same SQL runs on MySQL, PostgreSQL and SQLite.
     */
    public static function forQuery(Builder $query): self
    {
        $aggregates = $query->clone()->toBase()->selectRaw(
            'count(*) as total_count,'
            . ' sum(case when status_code >= 400 then 1 else 0 end) as error_count,'
            . ' sum(case when has_slow_queries then 1 else 0 end) as slow_count,'
            . ' avg(duration_ms) as average_duration,'
            . ' max(duration_ms) as max_duration'
        )->first();

        return new self(
            total: (int) ($aggregates->total_count ?? 0),
            errorCount: (int) ($aggregates->error_count ?? 0),
            slowCount: (int) ($aggregates->slow_count ?? 0),
            averageDurationMs: round((float) ($aggregates->average_duration ?? 0), 2),
            maxDurationMs: round((float) ($aggregates->max_duration ?? 0), 2),
        );
    }

    /** Share of requests that returned a 4xx or 5xx, as a percentage. */
    public function errorRate(): float
    {
        if ($this->total === 0) {
            return 0.0;
        }

        return round($this->errorCount / $this->total * 100, 1);
    }
}
