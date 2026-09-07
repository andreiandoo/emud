<?php

namespace App\Storefront;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Resolves which vehicle the storefront should personalise for.
 *
 * A signed-in customer's garage supplies the vehicle by default, but an explicit choice made
 * during the visit always wins — including the explicit choice to browse without one. Those two
 * are different states: an absent session entry means "nothing chosen yet, fall back to the
 * garage", while a null entry means "the customer deliberately cleared it". Collapsing them
 * would make the clear button useless for exactly the customers who own a garage.
 */
class VehicleContext
{
    private const SESSION_KEY = 'storefront.vehicle';

    public function current(): ?SelectedVehicle
    {
        if (Session::has(self::SESSION_KEY)) {
            $stored = Session::get(self::SESSION_KEY);

            return is_array($stored) ? SelectedVehicle::fromArray($stored) : null;
        }

        return $this->fromGarage();
    }

    public function select(SelectedVehicle $vehicle): void
    {
        Session::put(self::SESSION_KEY, $vehicle->toArray());
    }

    /**
     * Records that the customer wants no vehicle applied, rather than merely forgetting the
     * selection, so the garage does not immediately reinstate one.
     */
    public function clear(): void
    {
        Session::put(self::SESSION_KEY, null);
    }

    /**
     * Drops the explicit choice so the garage decides again. Used after garage changes, where
     * the customer's own edit is the more recent intent.
     */
    public function resetToGarage(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    public function has(): bool
    {
        return $this->current() !== null;
    }

    private function fromGarage(): ?SelectedVehicle
    {
        $user = Auth::user();

        if ($user === null) {
            return null;
        }

        // Ordering rather than filtering on is_primary: nothing in the schema guarantees a
        // single primary vehicle per user, so this stays deterministic even if two are flagged.
        $vehicle = $user->vehicles()
            ->with(['make', 'model', 'generation'])
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->first();

        return $vehicle === null ? null : SelectedVehicle::fromCustomerVehicle($vehicle);
    }
}
