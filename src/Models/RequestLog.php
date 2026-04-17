<?php

namespace AmjadAH\LaraScope\Models;

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
        'queries',
        'request_headers',
        'request_body',
        'response_body',
        'created_at',
    ];

    protected $casts = [
        'queries'         => 'array',
        'request_headers' => 'array',
        'request_body'    => 'array',
        'response_body'   => 'array',
        'created_at'      => 'datetime',
    ];

    /**
     * Determine whether any recorded query is flagged as slow.
     */
    public function hasSlowQueries(): bool
    {
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
