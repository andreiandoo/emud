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
    public function __construct(private AddressBook $book) {}

    /** @return array{city: ?string, county: ?string} */
    public function locationOf(User $user): array
    {
        $address = $this->book->shipping($user);

        return [
            'city' => ($address?->city ?? '') !== '' ? (string) $address->city : null,
            'county' => ($address?->county ?? '') !== '' ? (string) $address->county : null,
        ];
    }

    /** @return Collection<int, ServiceShop> */
    public function forUser(User $user, ?CustomerVehicle $vehicle = null, int $limit = 4): Collection
    {
        ['city' => $city, 'county' => $county] = $this->locationOf($user);

        if ($city === null && $county === null) {
            return collect();
        }

        $citySlug = $city === null ? null : Str::slug($city);
        $makeId = $vehicle?->make_id;

        return ServiceShop::query()
            ->published()
            ->with(['hours', 'services', 'makes'])
            ->where(fn (Builder $query) => $query
                ->when($citySlug !== null, fn (Builder $inner) => $inner->orWhere('city_slug', $citySlug))
                ->when($county !== null, fn (Builder $inner) => $inner->orWhereRaw('lower(county) = ?', [mb_strtolower((string) $county)])))
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
}
