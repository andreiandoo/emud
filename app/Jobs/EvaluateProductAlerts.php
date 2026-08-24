<?php

namespace App\Jobs;

use App\Models\ProductAlert;
use App\Notifications\ProductAlertTriggered;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EvaluateProductAlerts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $productId)
    {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        ProductAlert::query()
            ->with(['user', 'product', 'variant'])
            ->where('product_id', $this->productId)
            ->where('is_active', true)
            ->each(function (ProductAlert $alert): void {
                $conditionMet = match ($alert->type) {
                    'price' => $alert->variant && $alert->target_price !== null
                        && (float) $alert->variant->retail_price <= (float) $alert->target_price,
                    'back_in_stock' => $alert->product->supplierProducts()
                        ->where(function ($query) use ($alert): void {
                            $query->when($alert->variant_id, fn ($q) => $q->where('variant_id', $alert->variant_id));
                        })
                        ->whereHas('offer', fn ($query) => $query
                            ->whereIn('stock_status', ['in_stock', 'low_stock'])
                            ->where(fn ($q) => $q->whereNull('stock_quantity')->orWhere('stock_quantity', '>', 0))
                            ->where('stale_after', '>', now()))
                        ->exists(),
                    default => false,
                };

                if ($conditionMet && ! $alert->condition_met) {
                    $alert->user->notify(new ProductAlertTriggered($alert));
                    $alert->update(['condition_met' => true, 'last_triggered_at' => now()]);
                } elseif (! $conditionMet && $alert->condition_met) {
                    $alert->update(['condition_met' => false]);
                }
            });
    }
}
