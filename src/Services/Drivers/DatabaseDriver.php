<?php

namespace AmjadAH\LaraScope\Services\Drivers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DatabaseDriver
{
    /**
     * Persist a request log payload to the database.
     * If the insert fails (e.g. DB unavailable), the payload is written to
     * the default log channel so the record is never silently lost.
     *
     * @param  array<string, mixed>  $data
     */
    public function log(array $data): void
    {
        try {
            // Encode any array values as JSON strings for the query builder insert.
            $encodedData = array_map(
                static fn ($value) => is_array($value) ? json_encode($value) : $value,
                $data
            );

            $connection = config('larascope.database.connection');
            $table      = config('larascope.database.table', 'larascope_request_logs');

            DB::connection($connection)->table($table)->insert($encodedData);
        } catch (Throwable $throwable) {
            // Fall back to the application log so the record is not lost.
            Log::error('LaraScope: failed to persist request log to database.', [
                'exception' => $throwable->getMessage(),
                'payload'   => $data,
            ]);
        }
    }
}
