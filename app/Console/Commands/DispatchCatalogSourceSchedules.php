<?php

namespace App\Console\Commands;

use App\Jobs\SyncCatalogSource;
use App\Models\CatalogSourceSchedule;
use Cron\CronExpression;
use Illuminate\Console\Command;

class DispatchCatalogSourceSchedules extends Command
{
    protected $signature = 'catalog:sources:dispatch-schedules';

    protected $description = 'Dispatch catalog source sync jobs whose cron expressions are due.';

    public function handle(): int
    {
        $now = now();
        $count = 0;

        CatalogSourceSchedule::query()
            ->with('source')
            ->where('is_enabled', true)
            ->whereHas('source', fn ($query) => $query->where('is_active', true))
            ->get()
            ->each(function (CatalogSourceSchedule $schedule) use ($now, &$count): void {
                $localNow = $now->copy()->timezone($schedule->timezone);
                $cron = new CronExpression($schedule->cron_expression);

                if (! $cron->isDue($localNow)) {
                    return;
                }

                if ($schedule->last_dispatched_at?->isSameMinute($now)) {
                    return;
                }

                SyncCatalogSource::dispatch($schedule->catalog_source_id, $schedule->mode);
                $schedule->update(['last_dispatched_at' => $now]);
                $count++;
            });

        $this->info("Dispatched {$count} catalog source schedule(s).");

        return self::SUCCESS;
    }
}
