<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\AnalyticsService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateDailyAnalyticsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Carbon $date) {}

    public function handle(AnalyticsService $analyticsService): void
    {
        User::query()
            ->whereNull('owner_id')
            ->each(function (User $user) use ($analyticsService) {
                $stats = $analyticsService->computeDailyStats($user, $this->date);
                $analyticsService->upsertDailyStat($user->id, $this->date, $stats);
            });
    }
}
