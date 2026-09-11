<?php

namespace App\Directory;

use App\Models\CustomerVehicle;
use App\Models\ServiceShop;
use App\Models\User;
use App\Storefront\AddressBook;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Workshops near a customer, from the town on their account.
 *
 * The town is the delivery address's: where the customer lives or works, and the only place we
 * know them to be. The same town comes first, then the rest of the county; within that, the
 * ones that work on the customer's make, then paid placements, then name.
 */
class NearbyShops
{
    /** @var array<int, array{city: ?string, county: ?string}> per user, for the length of a request */
    private array $locations = [];

    public function __construct(private AddressBook $book) {}

    /** @return array{city: ?string, county: ?string} */
    public function locationOf(User $user): array
    {
        if (isset($this->locations[$user->id])) {
            return $this->locations[$user->id];
        }

        $address = $this->book->shipping($user);

        return $this->locations[$user->id] = [
            'city' => ($address?->city ?? '') !== '' ? (string) $address->city : null,
            'county' => ($address?->county ?? '') !== '' ? (string) $address->county : null,
        ];
    }

    /** @return Collection<int, ServiceShop> */
    public function forUser(User $user, ?CustomerVehicle $vehicle = null, int $limit = 4): Collection
    {
        $query = $this->around($user);

        if ($query === null) {
            return collect();
        }

        $city = $this->locationOf($user)['city'];
        $citySlug = $city === null ? null : Str::slug($city);
        $makeId = $vehicle?->make_id;

        return $query
            ->with(['hours', 'services', 'makes'])
            ->when($citySlug !== null, fn (Builder $query) => $query->orderByRaw('case when city_slug = ? then 0 else 1 end', [$citySlug]))
            ->when($makeId !== null, fn (Builder $query) => $query->orderByRaw(
                'case when exists (select 1 from service_shop_vehicle_make m where m.service_shop_id = service_shops.id and m.vehicle_make_id = ?) then 0 else 1 end',
                [$makeId],
            ))
            ->promotedFirst()
            ->orderByDesc('fits_parts_bought_here')
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    /** How many workshops the recommendations are drawn from: the same town and the rest of the county. */
    public function countForUser(User $user): int
    {
        return $this->around($user)?->count() ?? 0;
    }

    /** The directory page that lists all of them: the county's, or the town's when no county is known. */
    public function directoryUrl(User $user): ?string
    {
        ['city' => $city, 'county' => $county] = $this->locationOf($user);

        return match (true) {
            $county !== null => route('storefront.services', ['county' => $county]),
            $city !== null && Str::slug($city) !== '' => route('storefront.services.city', Str::slug($city)),
            default => null,
        };
    }

    /** Published workshops in the customer's town or county, or null when we know neither. */
    private function around(User $user): ?Builder
    {
        ['city' => $city, 'county' => $county] = $this->locationOf($user);

        if ($city === null && $county === null) {
            return null;
        }

        $citySlug = $city === null ? null : Str::slug($city);

        return ServiceShop::query()
            ->published()
            ->where(fn (Builder $query) => $query
                ->when($citySlug !== null, fn (Builder $inner) => $inner->orWhere('city_slug', $citySlug))
                ->when($county !== null, fn (Builder $inner) => $inner->orWhereRaw('lower(county) = ?', [mb_strtolower((string) $county)])));
    }
}
