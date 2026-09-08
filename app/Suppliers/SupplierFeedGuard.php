<?php

namespace App\Suppliers;

use App\Enums\SyncStatus;
use App\Models\Supplier;
use App\Models\SupplierSyncRun;

/**
 * Refuses to treat a suspiciously small feed as the supplier's full catalogue.
 *
 * A truncated download, a half-written export or an expired filter on the
 * supplier's side all look identical to a legitimate run: the file parses, the
 * rows import, nothing throws. Without this check the retirement sweep would then
 * mark everything the feed no longer mentions as discontinued and quietly empty
 * the shop.
 */
class SupplierFeedGuard
{
    /**
     * @return array{tripped: bool, reason: ?string, received: int, baseline: ?int, ratio: ?float, baseline_run_id: ?int}
     */
    public function evaluate(Supplier $supplier, string $mode, int $received): array
    {
        $settings = $supplier->settings['volume_guard'] ?? [];
        $config = config('emud.suppliers.volume_guard');

        $enabled = (bool) ($settings['enabled'] ?? $config['enabled']);
        $minimumBaseline = (int) ($settings['minimum_baseline_records'] ?? $config['minimum_baseline_records']);
        $minimumRatio = (float) ($settings['minimum_ratio'] ?? $config['minimum_ratio']);

        $result = ['tripped' => false, 'reason' => null, 'received' => $received, 'baseline' => null, 'ratio' => null, 'baseline_run_id' => null];

        if (! $enabled) {
            return [...$result, 'reason' => 'disabled'];
        }

        $baselineRun = $this->baseline($supplier, $mode);

        // A first run, or a supplier whose feed is genuinely tiny, has nothing to
        // compare against. Guessing there would block legitimate onboarding.
        if (! $baselineRun || (int) $baselineRun->received_count < $minimumBaseline) {
            return [...$result, 'reason' => 'no_baseline'];
        }

        $baseline = (int) $baselineRun->received_count;
        $ratio = $baseline > 0 ? $received / $baseline : 1.0;

        return [
            'tripped' => $ratio < $minimumRatio,
            'reason' => $ratio < $minimumRatio ? 'record_count_drop' : 'within_tolerance',
            'received' => $received,
            'baseline' => $baseline,
            'ratio' => round($ratio, 4),
            'baseline_run_id' => $baselineRun->id,
        ];
    }

    private function baseline(Supplier $supplier, string $mode): ?SupplierSyncRun
    {
        return SupplierSyncRun::query()
            ->where('supplier_id', $supplier->id)
            ->where('mode', $mode)
            ->whereIn('status', [SyncStatus::Completed->value, SyncStatus::CompletedWithErrors->value])
            ->latest('finished_at')
            ->first();
    }
}
