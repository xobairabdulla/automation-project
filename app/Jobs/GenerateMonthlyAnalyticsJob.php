<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\AnalyticsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateMonthlyAnalyticsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $year,
        public readonly int $month
    ) {}

    public function handle(AnalyticsService $analyticsService): void
    {
        User::query()
            ->whereNull('owner_id')
            ->each(function (User $user) use ($analyticsService) {
                $stats = $analyticsService->computeMonthlyStats($user, $this->year, $this->month);
                $analyticsService->upsertMonthlyStat($user->id, $this->year, $this->month, $stats);
            });
    }
}
