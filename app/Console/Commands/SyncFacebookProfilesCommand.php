<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\ConversationService;
use App\Services\Facebook\FacebookUserProfileService;
use Illuminate\Console\Command;

class SyncFacebookProfilesCommand extends Command
{
    protected $signature = 'facebook:sync-profiles
                            {--limit=100 : Maximum customers to process per run}
                            {--force : Re-sync even if profile was synced recently}';

    protected $description = 'Fetch Facebook sender names and profile pictures for customers missing them';

    public function handle(ConversationService $conversationService, FacebookUserProfileService $profileService): int
    {
        $limit = (int) $this->option('limit');
        $force = (bool) $this->option('force');

        $query = Customer::query()
            ->whereNotNull('external_customer_id')
            ->whereHas('facebookPage', fn ($q) => $q->whereNotNull('page_access_token_encrypted'))
            ->with('facebookPage:id,page_access_token_encrypted');

        if (! $force) {
            $query->where(fn ($q) => $q
                ->whereNull('profile_synced_at')
                ->orWhere('profile_synced_at', '<', now()->subHours(24))
                ->orWhereNull('name')
                ->orWhereNull('profile_picture_url')
            );
        }

        $customers = $query->limit($limit)->get();

        if ($customers->isEmpty()) {
            $this->info('No customers need profile sync.');

            return self::SUCCESS;
        }

        $this->info("Syncing {$customers->count()} customer profile(s)...");
        $bar = $this->output->createProgressBar($customers->count());
        $bar->start();

        $success = 0;
        $failed = 0;

        foreach ($customers as $customer) {
            $page = $customer->facebookPage;

            if (! $page) {
                $failed++;
                $bar->advance();

                continue;
            }

            if ($force) {
                $profileService->bustCache($customer->external_customer_id);
            }

            $before = $customer->profile_synced_at?->toDateTimeString();
            $conversationService->syncCustomerProfile($customer, $page, $profileService);
            $customer->refresh();

            if ($customer->profile_synced_at && $customer->profile_synced_at->toDateTimeString() !== $before) {
                $success++;
            } else {
                $failed++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Done. Success: {$success} | Failed/skipped: {$failed}");

        return self::SUCCESS;
    }
}
