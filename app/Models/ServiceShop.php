<?php

namespace App\Models;

use App\Enums\ServicePromotionTier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceShop extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'specialities' => 'array',
            'fits_parts_bought_here' => 'boolean',
            'promotion_tier' => ServicePromotionTier::class,
            'promoted_until' => 'date',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
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
}
