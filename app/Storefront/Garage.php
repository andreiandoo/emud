<?php

namespace App\Storefront;

use App\Models\CustomerVehicle;
use App\Models\User;
use App\Models\VehicleCollection;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The customer's saved vehicles.
 *
 * A partial unique index guarantees at most one primary vehicle per customer, so every write
 * that can set the flag demotes the previous holder inside the same transaction. Doing it in
 * two statements without a transaction would leave a window where the index rejects the
 * second write and the customer ends up with no primary vehicle at all.
 */
class Garage
{
    public function __construct(private VehicleContext $context) {}

    /** @return Collection<int, CustomerVehicle> */
    public function forUser(User $user): Collection
    {
        return $user->vehicles()
            ->with(['make', 'model', 'generation', 'collection'])
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->get();
    }

    /** @param array<string, mixed> $attributes */
    public function add(User $user, array $attributes): CustomerVehicle
    {
        // The first vehicle a customer saves becomes primary on its own; asking them to pick
        // one out of a list of one would be noise.
        $isPrimary = (bool) ($attributes['is_primary'] ?? false) || ! $user->vehicles()->exists();

        $vehicle = DB::transaction(function () use ($user, $attributes, $isPrimary): CustomerVehicle {
            if ($isPrimary) {
                $this->demoteOthers($user);
            }

            return $user->vehicles()->create([
                ...$attributes,
                'is_primary' => $isPrimary,
                ...$this->collectionFor($attributes),
            ]);
        });

        $this->refreshContext();

        return $vehicle;
    }

    /** @param array<string, mixed> $attributes */
    public function update(CustomerVehicle $vehicle, array $attributes): CustomerVehicle
    {
        DB::transaction(function () use ($vehicle, $attributes): void {
            if ((bool) ($attributes['is_primary'] ?? false)) {
                $this->demoteOthers($vehicle->user_id, $vehicle->id);
            }

            // Re-resolved on every edit: changing the model changes which collection — and so
            // which picture — belongs to this car.
            $vehicle->update([...$attributes, ...$this->collectionFor($attributes)]);
        });

        $this->refreshContext();

        return $vehicle->refresh();
    }

    public function makePrimary(CustomerVehicle $vehicle): void
    {
        DB::transaction(function () use ($vehicle): void {
            $this->demoteOthers($vehicle->user_id, $vehicle->id);
            $vehicle->update(['is_primary' => true]);
        });

        $this->refreshContext();
    }

    public function remove(CustomerVehicle $vehicle): void
    {
        DB::transaction(function () use ($vehicle): void {
            $wasPrimary = (bool) $vehicle->is_primary;
            $userId = $vehicle->user_id;
            $vehicle->delete();

            // Leaving a customer with vehicles but no primary would make the storefront stop
            // personalising for someone who never asked it to.
            if ($wasPrimary) {
                $next = CustomerVehicle::query()->where('user_id', $userId)->orderBy('id')->first();
                $next?->update(['is_primary' => true]);
            }
        });

        $this->refreshContext();
    }

    /**
     * The editorial collection this car belongs to, which is where its picture in the garage
     * comes from. Absent when the shop has no page for that car — the garage then shows the
     * plain card it always did.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, int|null>
     */
    private function collectionFor(array $attributes): array
    {
        // A partial update — a nickname, a mileage reading — says nothing about which car this
        // is, so recomputing from it would clear a perfectly good link.
        if (! array_intersect(['make_id', 'model_id', 'generation_id'], array_keys($attributes))) {
            return [];
        }

        $collection = VehicleCollection::forVehicle(
            isset($attributes['make_id']) ? (int) $attributes['make_id'] : null,
            isset($attributes['model_id']) ? (int) $attributes['model_id'] : null,
            isset($attributes['generation_id']) ? (int) $attributes['generation_id'] : null,
        );

        return ['vehicle_collection_id' => $collection?->id];
    }

    private function demoteOthers(User|int $user, ?int $except = null): void
    {
        CustomerVehicle::query()
            ->where('user_id', $user instanceof User ? $user->id : $user)
            ->when($except !== null, fn ($query) => $query->where('id', '!=', $except))
            ->where('is_primary', true)
            ->update(['is_primary' => false]);
    }

    /**
     * The customer editing their garage is a more recent statement of intent than whatever the
     * session was holding, so the storefront resolves the vehicle again from the garage.
     */
    private function refreshContext(): void
    {
        $this->context->resetToGarage();
    }
}
