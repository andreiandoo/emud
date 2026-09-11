<?php

namespace App\Storefront;

use Countable;

/**
 * The cars the storefront is answering for: one, or several a customer chose together from their
 * garage — the family car and the weekend 4x4 — so one search covers both.
 *
 * The first is the primary: the car a single-car answer speaks about, like the checkout's note
 * of which car an order was for.
 */
final readonly class VehicleSelection implements Countable
{
    /** @param non-empty-list<SelectedVehicle> $vehicles */
    public function __construct(public array $vehicles) {}

    public function primary(): SelectedVehicle
    {
        return $this->vehicles[0];
    }

    public function count(): int
    {
        return count($this->vehicles);
    }

    /** One car by its full name; several by make and model, so the line still fits. */
    public function label(): string
    {
        if (count($this->vehicles) === 1) {
            return $this->primary()->label();
        }

        $names = array_map(fn (SelectedVehicle $vehicle): string => trim($vehicle->makeName.' '.$vehicle->modelName), $this->vehicles);
        $last = array_pop($names);

        return implode(', ', $names).' și '.$last;
    }

    public function isFromGarage(): bool
    {
        foreach ($this->vehicles as $vehicle) {
            if (! $vehicle->isFromGarage()) {
                return false;
            }
        }

        return true;
    }

    /** @return list<int> */
    public function garageIds(): array
    {
        return array_values(array_filter(array_map(fn (SelectedVehicle $vehicle): ?int => $vehicle->customerVehicleId, $this->vehicles)));
    }
}
