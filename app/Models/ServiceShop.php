<?php

namespace App\Models;

use App\Directory\OpeningSchedule;
use App\Directory\ShopFacilities;
use App\Enums\ServicePromotionTier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceShop extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'specialities' => 'array',
            'amenities' => 'array',
            'payment_methods' => 'array',
            'certifications' => 'array',
            'fits_parts_bought_here' => 'boolean',
            'accepts_appointments' => 'boolean',
            'promotion_tier' => ServicePromotionTier::class,
            'promoted_until' => 'date',
            'registry_locked' => 'array',
            'registry_synced_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * The city slug is part of the public URL, so a row created by a seeder, an import or a test
     * has to get one too. Derived here rather than in the editor, which is only one of the ways
     * a workshop reaches the table.
     */
    protected static function booted(): void
    {
        static::saving(function (self $shop): void {
            if (($shop->city_slug ?? '') === '' && ($shop->city ?? '') !== '') {
                $shop->city_slug = str($shop->city)->slug()->value() ?: 'necunoscut';
            }
        });
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    /**
     * Paid position, expired promotions excluded, then name. Written as SQL rather than sorted
     * in PHP so the ordering survives pagination: sorting a page in memory would rank twenty
     * rows against each other instead of the whole directory.
     */
    public function scopePromotedFirst(Builder $query): Builder
    {
        return $query->orderByRaw(
            "case when promoted_until is not null and promoted_until < current_date then 0
                  when promotion_tier = 'premium' then 3
                  when promotion_tier = 'featured' then 2
                  when promotion_tier = 'listed' then 1
                  else 0 end desc"
        );
    }

    public function scopeOpenNow(Builder $query): Builder
    {
        [$sql, $bindings] = OpeningSchedule::openNowConstraint();

        return $query->whereRaw($sql, $bindings);
    }

    public function hours(): HasMany
    {
        return $this->hasMany(ServiceShopHour::class)->orderBy('weekday');
    }

    public function media(): HasMany
    {
        return $this->hasMany(ServiceShopMedium::class)->orderBy('position');
    }

    public function services(): BelongsToMany
    {
        // Named explicitly. Laravel builds a pivot name by sorting the two model names, which
        // gives service_service_shop — not the service_shop_service the migration creates.
        return $this->belongsToMany(Service::class, 'service_shop_service')
            ->withPivot(['price_from', 'price_to', 'currency', 'duration_minutes', 'note'])
            ->withTimestamps();
    }

    public function makes(): BelongsToMany
    {
        return $this->belongsToMany(VehicleMake::class, 'service_shop_vehicle_make')->orderBy('name');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(ServiceAppointment::class);
    }

    public function leadEvents(): HasMany
    {
        return $this->hasMany(ServiceShopLeadEvent::class);
    }

    /** The registry workshop this listing speaks for, when it was built from one. */
    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }

    /**
     * The registry-filled fields an admin has taken over.
     *
     * @return list<string>
     */
    public function lockedFields(): array
    {
        return array_values(array_filter((array) $this->registry_locked, 'is_string'));
    }

    /** Whether the registry still decides this field, rather than an admin. */
    public function followsRegistry(string $field): bool
    {
        return $this->workshop_id !== null && ! in_array($field, $this->lockedFields(), true);
    }

    /**
     * A promotion that has run out stops being a promotion. Checked at read time rather than by
     * a nightly job, so an expired placement can never keep its position because a job failed.
     */
    public function isPromoted(): bool
    {
        return $this->promotion_tier->isPaid()
            && ($this->promoted_until === null || ! $this->promoted_until->isPast());
    }

    public function effectiveTier(): ServicePromotionTier
    {
        return $this->isPromoted() ? $this->promotion_tier : ServicePromotionTier::None;
    }

    /** @return list<string> */
    public function specialityList(): array
    {
        return array_values(array_filter(array_map('strval', (array) $this->specialities)));
    }

    public function schedule(): OpeningSchedule
    {
        return OpeningSchedule::make($this->relationLoaded('hours') ? $this->hours : $this->hours()->get());
    }

    /** @return array<string, string> */
    public function amenityLabels(): array
    {
        return ShopFacilities::labels($this->amenities, ShopFacilities::AMENITIES);
    }

    /** @return array<string, string> */
    public function paymentLabels(): array
    {
        return ShopFacilities::labels($this->payment_methods, ShopFacilities::PAYMENT_METHODS);
    }

    /** @return array<string, string> */
    public function certificationLabels(): array
    {
        return ShopFacilities::labels($this->certifications, ShopFacilities::CERTIFICATIONS);
    }

    /**
     * The city segment of the public URL. Falls back to the slugged city so a listing saved
     * before the column existed still resolves rather than 404ing on a null path.
     */
    public function citySegment(): string
    {
        return $this->city_slug ?: (str($this->city)->slug()->value() ?: 'necunoscut');
    }

    public function url(): string
    {
        return route('storefront.service', ['city' => $this->citySegment(), 'slug' => $this->slug]);
    }

    /**
     * A link that opens the customer's own map application. Coordinates are used when we have
     * them because a pin is unambiguous; the written address is the fallback and is often good
     * enough for a workshop on a named street.
     */
    public function directionsUrl(): string
    {
        $destination = $this->latitude && $this->longitude
            ? "{$this->latitude},{$this->longitude}"
            : trim(implode(', ', array_filter([$this->address, $this->city, $this->county])));

        return 'https://www.google.com/maps/dir/?api=1&destination='.urlencode($destination);
    }
}
