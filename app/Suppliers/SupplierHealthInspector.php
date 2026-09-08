<?php

namespace App\Suppliers;

use App\Enums\SyncStatus;
use App\Models\Supplier;
use App\Models\SupplierOffer;
use App\Models\SupplierSyncRun;
use Illuminate\Support\Collection;

/**
 * Answers one question per supplier: would we notice today if this feed silently
 * stopped telling the truth?
 *
 * Every check is derived from data the sync already writes, so nothing here needs
 * a network call and it can run as often as the scheduler likes.
 */
class SupplierHealthInspector
{
    /** @return Collection<int, array{supplier: Supplier, healthy: bool, issues: list<array{code: string, message: string}>}> */
    public function inspectAll(): Collection
    {
        return Supplier::query()
            ->with('syncSchedules')
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Supplier $supplier): array => $this->inspect($supplier));
    }

    /** @return array{supplier: Supplier, healthy: bool, issues: list<array{code: string, message: string}>} */
    public function inspect(Supplier $supplier): array
    {
        $issues = [];
        $health = config('emud.suppliers.health');

        if (! $supplier->last_successful_sync_at) {
            $issues[] = ['code' => 'never_synced', 'message' => 'Furnizorul este activ, dar nu a avut niciodată o sincronizare reușită.'];
        }

        foreach (['catalog', 'prices', 'stock'] as $mode) {
            $issues = [...$issues, ...$this->inspectMode($supplier, $mode, (int) $health["{$mode}_stale_after_hours"], (float) $health['error_rate_threshold'])];
        }

        $staleOffers = SupplierOffer::query()
            ->whereHas('supplierProduct', fn ($query) => $query->where('supplier_id', $supplier->id)->whereNull('discontinued_at'))
            ->whereNotNull('stale_after')
            ->where('stale_after', '<', now())
            ->count();

        if ($staleOffers > 0) {
            $issues[] = ['code' => 'stale_offers', 'message' => "{$staleOffers} oferte au depășit termenul de prospețime și nu mai pot fi vândute."];
        }

        return ['supplier' => $supplier, 'healthy' => $issues === [], 'issues' => $issues];
    }

    /** @return list<array{code: string, message: string}> */
    private function inspectMode(Supplier $supplier, string $mode, int $staleAfterHours, float $errorRateThreshold): array
    {
        // Only judge a mode the supplier is actually scheduled for. Nobody runs a
        // stock feed for a supplier that only ships a daily catalogue.
        $scheduled = $supplier->syncSchedules->firstWhere('mode', $mode);
        if (! $scheduled?->is_enabled) {
            return [];
        }

        $lastRun = SupplierSyncRun::query()
            ->where('supplier_id', $supplier->id)
            ->where('mode', $mode)
            ->latest('created_at')
            ->first();

        if (! $lastRun) {
            return [['code' => "{$mode}_never_ran", 'message' => "Sincronizarea „{$mode}” este programată, dar nu a rulat niciodată."]];
        }

        $issues = [];

        if ($lastRun->status->needsAttention()) {
            $issues[] = [
                'code' => "{$mode}_last_run_{$lastRun->status->value}",
                'message' => "Ultima rulare „{$mode}” s-a încheiat cu starea „{$lastRun->status->label()}”.",
            ];
        }

        $lastSuccess = SupplierSyncRun::query()
            ->where('supplier_id', $supplier->id)
            ->where('mode', $mode)
            ->where('status', SyncStatus::Completed->value)
            ->latest('finished_at')
            ->first();

        if (! $lastSuccess || $lastSuccess->finished_at?->lt(now()->subHours($staleAfterHours))) {
            $age = $lastSuccess?->finished_at?->diffForHumans() ?? 'niciodată';
            $issues[] = ['code' => "{$mode}_stale", 'message' => "Ultima rulare „{$mode}” reușită complet: {$age} (prag {$staleAfterHours}h)."];
        }

        if ($lastRun->errorRate() > $errorRateThreshold) {
            $percent = round($lastRun->errorRate() * 100, 2);
            $issues[] = ['code' => "{$mode}_error_rate", 'message' => "Rata de eroare la ultima rulare „{$mode}” este {$percent}%."];
        }

        return $issues;
    }
}
