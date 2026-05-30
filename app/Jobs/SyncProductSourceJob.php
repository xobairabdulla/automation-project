<?php

namespace App\Jobs;

use App\Models\ProductSource;
use App\Services\ProductSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncProductSourceJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $backoff = 60;

    public function __construct(public readonly ProductSource $source) {}

    public function handle(ProductSyncService $syncService): void
    {
        try {
            $count = $syncService->sync($this->source);

            Log::info("SyncProductSourceJob: synced {$count} products", [
                'source_id' => $this->source->id,
                'user_id' => $this->source->user_id,
                'source_type' => $this->source->source_type,
            ]);
        } catch (\Throwable $e) {
            Log::error('SyncProductSourceJob: failed', [
                'source_id' => $this->source->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SyncProductSourceJob permanently failed', [
            'source_id' => $this->source->id,
            'error' => $e->getMessage(),
        ]);
    }
}
