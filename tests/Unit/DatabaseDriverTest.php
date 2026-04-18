<?php

namespace AmjadAH\LaraScope\Tests\Unit;

use AmjadAH\LaraScope\Services\Drivers\DatabaseDriver;
use AmjadAH\LaraScope\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DatabaseDriverTest extends TestCase
{
    private DatabaseDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = new DatabaseDriver();
    }

    public function test_log_inserts_record_to_database(): void
    {
        $payload = [
            'method'          => 'GET',
            'url'             => 'http://localhost/api/users',
            'path'            => 'api/users',
            'route_name'      => null,
            'ip_address'      => '127.0.0.1',
            'user_id'         => null,
            'status_code'     => 200,
            'duration_ms'     => 42.5,
            'memory_peak_mb'  => 12.0,
            'query_count'     => 0,
            'queries'         => null,
            'request_headers' => null,
            'request_body'    => null,
            'response_body'   => null,
            'created_at'      => now()->toDateTimeString(),
        ];

        $this->driver->log($payload);

        $this->assertDatabaseHas('larascope_request_logs', [
            'method'      => 'GET',
            'path'        => 'api/users',
            'status_code' => 200,
        ]);
    }

    public function test_log_encodes_array_fields_as_json(): void
    {
        $queries = [['sql' => 'select 1', 'bindings' => [], 'time_ms' => 1.0, 'slow' => false]];

        $payload = [
            'method'          => 'POST',
            'url'             => 'http://localhost/api/items',
            'path'            => 'api/items',
            'route_name'      => null,
            'ip_address'      => '127.0.0.1',
            'user_id'         => null,
            'status_code'     => 201,
            'duration_ms'     => 10.0,
            'memory_peak_mb'  => 8.0,
            'query_count'     => 1,
            'queries'         => $queries,
            'request_headers' => ['content-type' => 'application/json'],
            'request_body'    => null,
            'response_body'   => null,
            'created_at'      => now()->toDateTimeString(),
        ];

        $this->driver->log($payload);

        $row = DB::table('larascope_request_logs')->where('method', 'POST')->first();

        $this->assertNotNull($row);
        $this->assertJson($row->queries);
        $this->assertJson($row->request_headers);
    }

    public function test_log_falls_back_to_application_log_on_db_failure(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->with('LaraScope: failed to persist request log to database.', $this->arrayHasKey('exception'));

        // Point to a non-existent table to trigger a DB failure.
        $this->app['config']->set('larascope.database.table', 'non_existent_table_xyz');

        $payload = [
            'method'      => 'GET',
            'url'         => 'http://localhost/',
            'path'        => '/',
            'created_at'  => now()->toDateTimeString(),
        ];

        $this->driver->log($payload);
    }
}
