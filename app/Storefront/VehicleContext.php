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
 *
 * A customer can also choose several of their garage cars at once; listings and search then show
 * what fits any of them. current() stays one car — the first chosen — for the answers that are
 * about a single car, like the car an order is noted for.
 */
class VehicleContext
{
    private const SESSION_KEY = 'storefront.vehicle';

    private const FILTER_KEY = 'storefront.vehicle-filter';

    /**
     * Whether listings, search and the home page show only what fits the selected car.
     *
     * One setting for the whole shop rather than a checkbox per page: a customer who unticks it
     * once has said what they want and should not be asked again on the next page. It lives in
     * the session, and on the account of someone signed in so it outlasts the visit too.
     */
    public function filtersParts(): bool
    {
        if (Session::has(self::FILTER_KEY)) {
            return (bool) Session::get(self::FILTER_KEY);
        }

        return (bool) (Auth::user()?->filters_parts_by_vehicle ?? true);
    }

    public function setFiltersParts(bool $on): void
    {
        Session::put(self::FILTER_KEY, $on);

        $user = Auth::user();

        if ($user !== null && (bool) ($user->filters_parts_by_vehicle ?? true) !== $on) {
            $user->forceFill(['filters_parts_by_vehicle' => $on])->save();
        }
    }

    public function current(): ?SelectedVehicle
    {
        return $this->selection()?->primary();
    }

    /** Every car the storefront answers for: the one current() returns, or several chosen together. */
    public function selection(): ?VehicleSelection
    {
        // exists(), not has(): has() reports false for a null value, which would erase the
        // difference between "nothing chosen yet" and "deliberately cleared".
        if (Session::exists(self::SESSION_KEY)) {
            return $this->stored(Session::get(self::SESSION_KEY));
        }

        $vehicle = $this->fromGarage();

        return $vehicle === null ? null : new VehicleSelection([$vehicle]);
    }

    public function select(SelectedVehicle $vehicle): void
    {
        Session::put(self::SESSION_KEY, $vehicle->toArray());
    }

    /**
     * Several cars at once, in the order given; the first becomes current(). One car is stored
     * the way select() stores it, so nothing reading the session has to tell the two apart.
     *
     * @param  list<SelectedVehicle>  $vehicles
     */
    public function selectMany(array $vehicles): void
    {
        $vehicles = array_values($vehicles);

        if ($vehicles === []) {
            $this->clear();
        } elseif (count($vehicles) === 1) {
            $this->select($vehicles[0]);
        } else {
            Session::put(self::SESSION_KEY, ['several' => array_map(fn (SelectedVehicle $vehicle): array => $vehicle->toArray(), $vehicles)]);
        }
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

    private function stored(mixed $stored): ?VehicleSelection
    {
        if (! is_array($stored)) {
            return null;
        }

        $rows = isset($stored['several']) && is_array($stored['several']) ? $stored['several'] : [$stored];

        $vehicles = array_values(array_filter(array_map(
            fn (mixed $row): ?SelectedVehicle => is_array($row) ? SelectedVehicle::fromArray($row) : null,
            $rows,
        )));

        return $vehicles === [] ? null : new VehicleSelection($vehicles);
    }

    private function fromGarage(): ?SelectedVehicle
    {
        $user = Auth::user();

        if ($user === null) {
            return null;
        }

        // Ordering rather than filtering on is_primary: a partial unique index guarantees at
        // most one primary vehicle, but not that one exists. A customer whose primary was
        // removed by something other than the garage service still gets their oldest vehicle
        // instead of losing personalisation entirely.
        $vehicle = $user->vehicles()
            ->with(['make', 'model', 'generation'])
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->first();

        return $vehicle === null ? null : SelectedVehicle::fromCustomerVehicle($vehicle);
    }
}
