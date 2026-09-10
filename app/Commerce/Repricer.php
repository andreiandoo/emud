<?php

namespace App\Commerce;

use App\Enums\PriceChangeStatus;
use App\Enums\PricingMode;
use App\Models\PriceChange;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Moves a product's shelf price when the cost under it moves.
 *
 * The price follows the offer routing would actually fulfil from, under the rule that
 * governs the product. A small move goes live on its own; a move bigger than the rule's
 * limit waits in the back office, because a feed error that halves a cost should not halve
 * a price before anyone has looked. A variant with no price at all is priced directly:
 * there is nothing to compare against and nothing to protect.
 *
 * Products an operator prices by hand are never touched.
 */
final class Repricer
{
    public function __construct(
        private readonly SupplierOfferRouter $router,
        private readonly PricingPolicy $policy,
        private readonly ShelfPriceCalculator $shelf,
    ) {}

    /** @return list<PriceChange> One per variant whose price was changed or proposed. */
    public function reprice(Product $product, string $trigger = 'cost_change'): array
    {
        if ($product->pricing_mode === PricingMode::Manual) {
            return [];
        }

        $chosen = $this->router->route($product, 1, (string) config('emud.pricing.reference_destination', 'RO'))->chosen();

        // No offer to sell from, or one whose cost cannot be completed: there is no honest
        // number to put on the shelf, so the current one stays and availability says the rest.
        if ($chosen === null || ! $chosen['landed']['complete']) {
            return [];
        }

        $policy = $this->policy->for($product, $chosen['supplier']);
        $quote = $this->shelf->price($chosen['offer'], $chosen['landed'], $policy);

        return $this->variantsToPrice($product, $chosen)
            ->map(fn (ProductVariant $variant): ?PriceChange => $this->decide($product, $variant, $quote, $policy, $chosen, $trigger))
            ->filter()
            ->values()
            ->all();
    }

    public function approve(PriceChange $change, ?int $userId = null): void
    {
        if ($change->status !== PriceChangeStatus::Pending) {
            throw new RuntimeException('Doar o schimbare de preț în așteptare poate fi aprobată.');
        }

        DB::transaction(function () use ($change, $userId): void {
            $change->variant()->firstOrFail()->update(['retail_price' => $change->new_price, 'currency' => $change->currency]);
            $change->update(['status' => PriceChangeStatus::Applied, 'decided_by' => $userId, 'decided_at' => now()]);
        });
    }

    public function reject(PriceChange $change, ?int $userId = null): void
    {
        if ($change->status !== PriceChangeStatus::Pending) {
            throw new RuntimeException('Doar o schimbare de preț în așteptare poate fi respinsă.');
        }

        $change->update(['status' => PriceChangeStatus::Rejected, 'decided_by' => $userId, 'decided_at' => now()]);
    }

    /**
     * @param  array<string, mixed>  $chosen
     * @return Collection<int, ProductVariant>
     */
    private function variantsToPrice(Product $product, array $chosen): Collection
    {
        $variantId = $chosen['supplier_product']->variant_id;

        return ProductVariant::query()
            ->where('product_id', $product->id)
            ->when($variantId, fn ($query) => $query->whereKey($variantId), fn ($query) => $query->where('is_active', true))
            ->get();
    }

    /**
     * @param  array<string, mixed>  $quote
     * @param  array<string, mixed>  $policy
     * @param  array<string, mixed>  $chosen
     */
    private function decide(Product $product, ProductVariant $variant, array $quote, array $policy, array $chosen, string $trigger): ?PriceChange
    {
        $old = $variant->retail_price === null ? null : (float) $variant->retail_price;
        $new = (float) $quote['price'];
        $sameCurrency = $variant->currency === $quote['currency'];

        if ($old !== null && $sameCurrency && abs($old - $new) < 0.005) {
            return null;
        }

        // A price in another currency is not comparable, so its move cannot be sized either.
        $movePercent = $old !== null && $old > 0 && $sameCurrency ? abs($new - $old) / $old * 100 : null;
        $needsReview = $old !== null && ($movePercent === null || $movePercent > $policy['max_auto_change_percent']);

        $decision = [
            'offer_id' => $chosen['offer']->id,
            'supplier_code' => $chosen['supplier']->code,
            'unit_landed_cost' => $chosen['landed']['unit_landed_cost'],
            'policy' => $policy,
            'binding' => $quote['binding'],
            'floors' => $quote['floors'],
            'contribution' => $quote['contribution'],
            'contribution_percent' => $quote['contribution_percent'],
            'move_percent' => $movePercent === null ? null : round($movePercent, 2),
        ];

        return DB::transaction(function () use ($product, $variant, $old, $new, $quote, $trigger, $decision, $needsReview): PriceChange {
            // Only the latest cost matters: an older proposal still waiting is replaced, not
            // left for someone to approve a price nobody would now choose.
            PriceChange::query()
                ->where('variant_id', $variant->id)
                ->where('status', PriceChangeStatus::Pending->value)
                ->update(['status' => PriceChangeStatus::Superseded->value, 'decided_at' => now()]);

            $change = PriceChange::query()->create([
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'old_price' => $old,
                'new_price' => $new,
                'currency' => $quote['currency'],
                'status' => $needsReview ? PriceChangeStatus::Pending : PriceChangeStatus::Applied,
                'trigger' => $trigger,
                'decision' => $decision,
                'decided_at' => $needsReview ? null : now(),
                'created_at' => now(),
            ]);

            if (! $needsReview) {
                $variant->update(['retail_price' => $new, 'currency' => $quote['currency']]);
            }

            return $change;
        });
    }
}
