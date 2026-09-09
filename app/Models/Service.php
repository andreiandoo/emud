<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A job a workshop performs.
 *
 * The link to a parts category is what makes this taxonomy worth having: without it the list is
 * a glossary, with it a page about replacing brake pads can offer brake pads.
 */
class Service extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function serviceCategory(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class);
    }

    /** The parts category this job consumes, when there is one. */
    public function partsCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function shops(): BelongsToMany
    {
        return $this->belongsToMany(ServiceShop::class)
            ->withPivot(['price_from', 'price_to', 'currency', 'duration_minutes', 'note'])
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
