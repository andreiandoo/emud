<?php

namespace App\Jobs;

use App\Commerce\Repricer;
use App\Enums\PriceChangeStatus;
use App\Models\PriceChange;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Re-prices one product after a cost it depends on moved.
 *
 * Unique per product: a feed touching the same product through several supplier rows in one
 * run needs one decision on the final numbers, not one per row.
 */
class RepriceProduct implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 600;

    public function __construct(public readonly int $productId, public readonly string $trigger = 'cost_change')
    {
        $this->onQueue('imports');
    }

    public function uniqueId(): string
    {
        return (string) $this->productId;
    }

    public function handle(Repricer $repricer): void
    {
        $product = Product::query()->find($this->productId);

        if (! $product) {
            return;
        }

        $changes = $repricer->reprice($product, $this->trigger);

        // A price that actually went live can satisfy a customer's price alert.
        if (collect($changes)->contains(fn (PriceChange $change): bool => $change->status === PriceChangeStatus::Applied)) {
            EvaluateProductAlerts::dispatch($product->id);
        }
    }
}
