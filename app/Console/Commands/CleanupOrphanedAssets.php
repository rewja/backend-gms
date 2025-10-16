<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Asset;

class CleanupOrphanedAssets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'assets:cleanup-orphans {--dry-run : List only, do not delete}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove assets that do not have an associated request';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $orphans = Asset::whereDoesntHave('request')->get();

        if ($orphans->isEmpty()) {
            $this->info('No orphaned assets found.');
            return self::SUCCESS;
        }

        $this->info('Found ' . $orphans->count() . ' orphaned assets.');

        if ($this->option('dry-run')) {
            foreach ($orphans as $asset) {
                $this->line('- ID: ' . $asset->id . ' | code: ' . ($asset->asset_code ?? 'N/A'));
            }
            $this->info('Dry run complete. No records deleted.');
            return self::SUCCESS;
        }

        $count = 0;
        foreach ($orphans as $asset) {
            $asset->delete();
            $count++;
        }

        $this->info("Deleted {$count} orphaned assets.");
        return self::SUCCESS;
    }
}


