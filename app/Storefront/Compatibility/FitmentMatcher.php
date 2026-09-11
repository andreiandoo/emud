<?php

namespace App\Storefront\Compatibility;

use App\Models\Product;
use App\Models\ProductFitment;
use App\Storefront\SelectedVehicle;
use App\Storefront\VehicleSelection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Decides whether a product fits the customer's selected vehicle.
 *
 * Fitment rows use null as a wildcard: a row with only a make_id covers every model of that
 * make. The awkward case is the customer who has not told us their generation — a row scoped to
 * one generation neither confirms nor excludes their car, and answering either way would be a
 * guess. That case gets its own verdict rather than being folded into a yes or a no.
 */
class FitmentMatcher
{
    /**
     * Narrows a product query to what could plausibly fit, keeping the uncertain cases in.
     * Excluding them would hide parts the customer can buy after confirming one detail.
     */
    /** One car, or everything the customer has chosen: listings hand over the whole choice. */
    public function scopeForVehicle(Builder $query, SelectedVehicle|VehicleSelection $vehicle): Builder
    {
        if ($vehicle instanceof VehicleSelection) {
            return $this->scopeForSelection($query, $vehicle);
        }

        return $query->where(function (Builder $query) use ($vehicle): void {
            $query->where('is_universal', true)
                ->orWhereHas('fitments', fn (Builder $fitment) => $this->constrainFitment($fitment, $vehicle));
        });
    }

    /** The same narrowing for several cars at once: whatever could fit any of them. */
    public function scopeForSelection(Builder $query, VehicleSelection $selection): Builder
    {
        if (count($selection) === 1) {
            return $this->scopeForVehicle($query, $selection->primary());
        }

        return $query->where(function (Builder $query) use ($selection): void {
            $query->where('is_universal', true)
                ->orWhereHas('fitments', fn (Builder $fitment) => $fitment->where(function (Builder $any) use ($selection): void {
                    foreach ($selection->vehicles as $vehicle) {
                        $any->orWhere(fn (Builder $one) => $this->constrainFitment($one, $vehicle));
                    }
                }));
        });
    }

    /** The most favourable answer across the cars: a part that fits one of them is shown as fitting. */
    public function verdictForSelection(Product $product, ?VehicleSelection $selection): CompatibilityVerdict
    {
        if ($selection === null) {
            return CompatibilityVerdict::Unknown;
        }

        $best = null;

        foreach ($selection->vehicles as $vehicle) {
            $verdict = $this->verdictFor($product, $vehicle);
            $best = $best === null ? $verdict : $this->preferred($best, $verdict);
        }

        return $best ?? CompatibilityVerdict::Unknown;
    }

    /**
     * The car of the selection the product suits best, with that verdict. A product page speaks
     * about one car, and it should be the one the part is for.
     *
     * @return array{0: SelectedVehicle, 1: CompatibilityVerdict}
     */
    public function bestFit(Product $product, VehicleSelection $selection): array
    {
        $best = [$selection->primary(), $this->verdictFor($product, $selection->primary())];

        foreach (array_slice($selection->vehicles, 1) as $vehicle) {
            $verdict = $this->verdictFor($product, $vehicle);

            if ($verdict !== $best[1] && $this->preferred($best[1], $verdict) === $verdict) {
                $best = [$vehicle, $verdict];
            }
        }

        return $best;
    }

    public function verdictFor(Product $product, SelectedVehicle|VehicleSelection|null $vehicle): CompatibilityVerdict
    {
        if ($vehicle instanceof VehicleSelection) {
            return $this->verdictForSelection($product, $vehicle);
        }

        if ($vehicle === null) {
            return CompatibilityVerdict::Unknown;
        }

        if ($product->is_universal) {
            return CompatibilityVerdict::Confirmed;
        }

        $fitments = $product->relationLoaded('fitments') ? $product->fitments : $product->fitments()->get();

        if ($fitments->isEmpty()) {
            return CompatibilityVerdict::Unknown;
        }

        $candidates = $fitments->filter(fn (ProductFitment $fitment) => $this->coversMakeAndModel($fitment, $vehicle));

        if ($candidates->isEmpty()) {
            // The product carries fitment data and none of it reaches this make/model.
            return CompatibilityVerdict::Incompatible;
        }

        return $this->bestVerdict($candidates, $vehicle);
    }

    /** @param Collection<int, ProductFitment> $candidates */
    private function bestVerdict(Collection $candidates, SelectedVehicle $vehicle): CompatibilityVerdict
    {
        $best = CompatibilityVerdict::Incompatible;

        foreach ($candidates as $fitment) {
            $verdict = $this->verdictForFitment($fitment, $vehicle);

            // A single confirmed row settles it; otherwise keep the most favourable answer any
            // row supports, since one row excluding the car does not mean another does.
            if ($verdict === CompatibilityVerdict::Confirmed) {
                return $verdict;
            }

            $best = $this->preferred($best, $verdict);
        }

        return $best;
    }

    private function verdictForFitment(ProductFitment $fitment, SelectedVehicle $vehicle): CompatibilityVerdict
    {
        if (! $this->yearMatches($fitment, $vehicle)) {
            // A year range we cannot evaluate is a missing detail, not an exclusion.
            return $vehicle->year === null && ($fitment->year_from || $fitment->year_to)
                ? CompatibilityVerdict::RequiresVehicleDetail
                : CompatibilityVerdict::Incompatible;
        }

        if ($fitment->generation_id !== null && $vehicle->generationId === null) {
            return CompatibilityVerdict::RequiresVehicleDetail;
        }

        if ($fitment->generation_id !== null && $fitment->generation_id !== $vehicle->generationId) {
            return CompatibilityVerdict::Incompatible;
        }

        return $fitment->requires_modification
            ? CompatibilityVerdict::Conditional
            : CompatibilityVerdict::Confirmed;
    }

    private function coversMakeAndModel(ProductFitment $fitment, SelectedVehicle $vehicle): bool
    {
        return ($fitment->make_id === null || (int) $fitment->make_id === $vehicle->makeId)
            && ($fitment->model_id === null || (int) $fitment->model_id === $vehicle->modelId);
    }

    private function yearMatches(ProductFitment $fitment, SelectedVehicle $vehicle): bool
    {
        if ($fitment->year_from === null && $fitment->year_to === null) {
            return true;
        }

        if ($vehicle->year === null) {
            return false;
        }

        return ($fitment->year_from === null || $vehicle->year >= (int) $fitment->year_from)
            && ($fitment->year_to === null || $vehicle->year <= (int) $fitment->year_to);
    }

    private function preferred(CompatibilityVerdict $current, CompatibilityVerdict $candidate): CompatibilityVerdict
    {
        $rank = [
            CompatibilityVerdict::Confirmed->value => 4,
            CompatibilityVerdict::Conditional->value => 3,
            CompatibilityVerdict::RequiresVehicleDetail->value => 2,
            CompatibilityVerdict::Unknown->value => 1,
            CompatibilityVerdict::Incompatible->value => 0,
        ];

        return $rank[$candidate->value] > $rank[$current->value] ? $candidate : $current;
    }

    private function constrainFitment(Builder $fitment, SelectedVehicle $vehicle): void
    {
        $fitment
            ->where(fn (Builder $q) => $q->whereNull('make_id')->orWhere('make_id', $vehicle->makeId))
            ->where(fn (Builder $q) => $q->whereNull('model_id')->orWhere('model_id', $vehicle->modelId));

        // With a known generation, rows scoped to a different one are excluded outright. Without
        // one, every generation row stays a candidate and the verdict reports the uncertainty.
        if ($vehicle->generationId !== null) {
            $fitment->where(fn (Builder $q) => $q->whereNull('generation_id')->orWhere('generation_id', $vehicle->generationId));
        }

        if ($vehicle->year !== null) {
            $fitment
                ->where(fn (Builder $q) => $q->whereNull('year_from')->orWhere('year_from', '<=', $vehicle->year))
                ->where(fn (Builder $q) => $q->whereNull('year_to')->orWhere('year_to', '>=', $vehicle->year));
        }
    }
}
