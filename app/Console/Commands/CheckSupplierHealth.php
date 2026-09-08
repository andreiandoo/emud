<?php

namespace App\Console\Commands;

use App\Models\Supplier;
use App\Models\User;
use App\Notifications\SupplierFeedUnhealthy;
use App\Suppliers\SupplierHealthInspector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

class CheckSupplierHealth extends Command
{
    protected $signature = 'suppliers:health-check {--notify : Email administrators about suppliers that need attention}';

    protected $description = 'Report active suppliers whose feeds failed, went stale or started rejecting rows.';

    public function handle(SupplierHealthInspector $inspector): int
    {
        $reports = $inspector->inspectAll();
        $unhealthy = $reports->reject(fn (array $report): bool => $report['healthy']);

        $this->table(
            ['Furnizor', 'Stare', 'Probleme'],
            $reports->map(fn (array $report): array => [
                $report['supplier']->code,
                $report['healthy'] ? 'ok' : 'atenție',
                $report['healthy'] ? '—' : implode("\n", array_column($report['issues'], 'message')),
            ])->all(),
        );

        if ($unhealthy->isEmpty()) {
            $this->info('Toți furnizorii activi sunt sănătoși.');

            return self::SUCCESS;
        }

        if ($this->option('notify')) {
            $unhealthy->each(fn (array $report) => $this->notify($report['supplier'], $report['issues']));
        }

        $this->warn("{$unhealthy->count()} furnizor(i) necesită atenție.");

        // A supplier problem is not a scheduler failure: exiting non-zero here would
        // make every monitoring tool treat a stale feed as a crashed command.
        return self::SUCCESS;
    }

    /** @param list<array{code: string, message: string}> $issues */
    private function notify(Supplier $supplier, array $issues): void
    {
        // One alert per supplier per problem signature per day, so a feed that stays
        // broken for a week does not send the same mail every scheduler tick.
        $signature = md5(implode('|', array_column($issues, 'code')));
        $key = "supplier-health:{$supplier->id}:{$signature}";

        if (! Cache::add($key, true, now()->addDay())) {
            return;
        }

        $admins = User::query()->where('role', 'admin')->get();

        if ($admins->isEmpty()) {
            $this->warn('Niciun administrator de notificat.');

            return;
        }

        Notification::send($admins, new SupplierFeedUnhealthy($supplier, $issues));
        $this->line("Notificare trimisă pentru {$supplier->code}.");
    }
}
