<?php

namespace App\Console\Commands;

use App\Services\RegionalDataSyncService;
use Illuminate\Console\Command;

class SyncRegionalData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:regional-data {--source=emsifa : The data source API to use} {--dry-run : Run sync without writing to database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize official Indonesian regional data (Provinces and Cities/Regencies) from external API';

    /**
     * Execute the console command.
     */
    public function handle(RegionalDataSyncService $syncService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $source = (string) $this->option('source');

        $this->info("Starting regional data sync from source: {$source}" . ($dryRun ? ' (DRY RUN)' : ''));

        $log = $syncService->sync($dryRun, $source);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Status', $log->status],
                ['Provinces Created', $log->provinces_created],
                ['Provinces Updated', $log->provinces_updated],
                ['Cities/Regencies Created', $log->cities_created],
                ['Cities/Regencies Updated', $log->cities_updated],
                ['Started At', $log->started_at?->toDateTimeString()],
                ['Completed At', $log->completed_at?->toDateTimeString()],
            ]
        );

        if ($log->errors) {
            $this->warn("Errors encountered during sync:");
            $this->error($log->errors);
        }

        if ($log->status === 'failed') {
            $this->error("Regional data sync failed.");
            return Command::FAILURE;
        }

        $this->info("Regional data sync finished successfully.");
        return Command::SUCCESS;
    }
}
