<?php

namespace Amjad\LaraScope\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RequestLog extends Model
{
    // created_at is managed manually; there is no updated_at column.
    public $timestamps = false;

    public function getTable(): string
    {
        return config('larascope.database.table', 'larascope_request_logs');
    }

    public function getConnectionName(): ?string
    {
        return config('larascope.database.connection') ?? parent::getConnectionName();
    }

    protected $fillable = [
        'method',
        'url',
        'path',
        'route_name',
        'ip_address',
        'user_id',
        'status_code',
        'duration_ms',
        'memory_peak_mb',
        'query_count',
        'has_slow_queries',
        'queries',
        'request_headers',
        'request_body',
        'response_body',
        'created_at',
    ];

    protected $casts = [
        'has_slow_queries' => 'boolean',
        'queries'         => 'array',
        'request_headers' => 'array',
        'request_body'    => 'array',
        'response_body'   => 'array',
        'created_at'      => 'datetime',
    ];

    /**
     * Determine whether any recorded query is flagged as slow.
     *
     * Persisted rows carry the answer in the indexed `has_slow_queries`
     * column, which is what the dashboard filters on. Models built in
     * memory (and rows predating the column) fall back to scanning the
     * `queries` JSON blob.
     */
    public function hasSlowQueries(): bool
    {
        if (array_key_exists('has_slow_queries', $this->attributes)) {
            return (bool) $this->has_slow_queries;
        }

        if (empty($this->queries)) {
            return false;
        }

        return collect($this->queries)->contains('slow', true);
    }

    /**
     * Scope: only requests that executed at least one SQL query.
     */
    public function scopeWithQueries(Builder $query): Builder
    {
        return $query->where('query_count', '>', 0);
    }
}
