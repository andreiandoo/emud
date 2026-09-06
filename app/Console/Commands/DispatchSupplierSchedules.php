<?php

namespace App\Console\Commands;

use App\Jobs\SyncSupplierFeed;
use App\Models\SupplierSyncSchedule;
use Cron\CronExpression;
use Illuminate\Console\Command;

class DispatchSupplierSchedules extends Command
{
    protected $signature = 'suppliers:dispatch-schedules';

    protected $description = 'Dispatch supplier sync jobs whose individual cron expressions are due.';

    public function handle(): int
    {
        $now = now();
        $count = 0;

        SupplierSyncSchedule::query()
            ->with('supplier')
            ->where('is_enabled', true)
            ->whereHas('supplier', fn ($query) => $query->where('is_active', true))
            ->get()
            ->each(function (SupplierSyncSchedule $schedule) use ($now, &$count): void {
                $localNow = $now->copy()->timezone($schedule->timezone);
                $cron = new CronExpression($schedule->cron_expression);

                if (! $cron->isDue($localNow)) {
                    return;
                }

                if ($schedule->last_dispatched_at?->isSameMinute($now)) {
                    return;
                }

                SyncSupplierFeed::dispatch($schedule->supplier_id, $schedule->mode);
                $schedule->update(['last_dispatched_at' => $now]);
                $count++;
            });

        $this->info("Dispatched {$count} supplier schedule(s).");

        return self::SUCCESS;
    }
}
