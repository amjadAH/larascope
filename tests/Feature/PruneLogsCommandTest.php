<?php

namespace Amjad\LaraScope\Tests\Feature;

use Amjad\LaraScope\Tests\TestCase;
use Illuminate\Support\Facades\DB;

class PruneLogsCommandTest extends TestCase
{
    public function test_prune_command_deletes_logs_older_than_retain_days(): void
    {
        $this->app['config']->set('larascope.pruning.retain_days', 30);

        // Old log — should be pruned.
        DB::table('larascope_request_logs')->insert(
            $this->makeRow(['created_at' => now()->subDays(45)->toDateTimeString()])
        );

        // Recent log — should be kept.
        DB::table('larascope_request_logs')->insert(
            $this->makeRow(['created_at' => now()->subDays(10)->toDateTimeString(), 'path' => 'api/recent'])
        );

        $this->artisan('larascope:prune')
            ->expectsOutputToContain('pruned 1 request log(s)')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('larascope_request_logs', ['path' => 'api/old']);
        $this->assertDatabaseHas('larascope_request_logs', ['path' => 'api/recent']);
    }

    public function test_prune_command_reports_zero_when_nothing_to_prune(): void
    {
        $this->app['config']->set('larascope.pruning.retain_days', 30);

        DB::table('larascope_request_logs')->insert(
            $this->makeRow(['created_at' => now()->toDateTimeString()])
        );

        $this->artisan('larascope:prune')
            ->expectsOutputToContain('pruned 0 request log(s)')
            ->assertExitCode(0);
    }

    public function test_prune_command_respects_retain_days_configuration(): void
    {
        $this->app['config']->set('larascope.pruning.retain_days', 7);

        DB::table('larascope_request_logs')->insert(
            $this->makeRow(['created_at' => now()->subDays(10)->toDateTimeString()])
        );

        $this->artisan('larascope:prune')
            ->expectsOutputToContain('older than 7 day(s)')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('larascope_request_logs', ['path' => 'api/old']);
    }

    /** @param array<string, mixed> $overrides */
    private function makeRow(array $overrides = []): array
    {
        return array_merge([
            'method'          => 'GET',
            'url'             => 'http://localhost/api/old',
            'path'            => 'api/old',
            'route_name'      => null,
            'ip_address'      => '127.0.0.1',
            'user_id'         => null,
            'status_code'     => 200,
            'duration_ms'     => 15.0,
            'memory_peak_mb'  => 5.0,
            'query_count'     => 0,
            'queries'         => null,
            'request_headers' => null,
            'request_body'    => null,
            'response_body'   => null,
            'created_at'      => now()->toDateTimeString(),
        ], $overrides);
    }
}
