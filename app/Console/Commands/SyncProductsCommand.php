<?php

namespace App\Console\Commands;

use App\Jobs\SyncProductSourceJob;
use App\Models\ProductSource;
use Illuminate\Console\Command;

class SyncProductsCommand extends Command
{
    protected $signature = 'products:sync {--user= : Only sync sources for this user ID}';

    protected $description = 'Sync products from all active product sources';

    public function handle(): int
    {
        $query = ProductSource::where('is_active', true)
            ->where('source_type', '!=', 'manual');

        if ($userId = $this->option('user')) {
            $query->where('user_id', $userId);
        }

        $sources = $query->get();

        if ($sources->isEmpty()) {
            $this->info('No active product sources to sync.');

            return self::SUCCESS;
        }

        foreach ($sources as $source) {
            SyncProductSourceJob::dispatch($source);
            $this->line("Queued sync for source #{$source->id} ({$source->name}, user #{$source->user_id})");
        }

        $this->info("Dispatched {$sources->count()} sync job(s).");

        return self::SUCCESS;
    }
}
