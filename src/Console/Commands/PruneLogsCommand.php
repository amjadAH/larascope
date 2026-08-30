<?php

namespace Amjad\LaraScope\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneLogsCommand extends Command
{
    protected $signature = 'larascope:prune';

    protected $description = 'Prune old LaraScope request logs from the database.';

    public function handle(): int
    {
        $retainDays = (int) config('larascope.pruning.retain_days', 30);
        $cutoffDate = now()->subDays($retainDays);

        $connection = config('larascope.database.connection');
        $table = config('larascope.database.table', 'larascope_request_logs');

        $deletedCount = DB::connection($connection)->table($table)
            ->where('created_at', '<', $cutoffDate)
            ->delete();

        $this->info("LaraScope: pruned {$deletedCount} request log(s) older than {$retainDays} day(s).");

        return self::SUCCESS;
    }
}
