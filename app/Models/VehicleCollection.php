<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * One car, presented.
 *
 * @see \Database\Seeders\VehicleCollectionSeeder for how the initial set is built
 * @see \App\Catalog\CollectionMatcher for how products end up attached
 */
class VehicleCollection extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'robots_index' => 'boolean',
            'robots_follow' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function make(): BelongsTo
    {
        return $this->belongsTo(VehicleMake::class, 'make_id');
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class, 'model_id');
    }

    public function generation(): BelongsTo
    {
        return $this->belongsTo(VehicleGeneration::class, 'generation_id');
    }

    /**
     * Pivot named rather than derived. Laravel builds the table name by sorting the two model
     * basenames, and while that happens to agree here, a rename on either side would silently
     * point the relation at a table nobody created.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_vehicle_collection')
            ->withPivot('is_automatic')
            ->withTimestamps();
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function customerVehicles(): HasMany
    {
        return $this->hasMany(CustomerVehicle::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /** Position first, then name, so an operator's ordering wins and ties stay stable. */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('name');
    }

    /**
     * The best collection for one concrete car: the generation's own if there is one, otherwise
     * the model's, otherwise the make's.
     *
     * Narrowest first, because a Jimny III page says more than a Suzuki page, and a car with no
     * collection at any level is normal rather than an error — the shop simply has no page for
     * it yet.
     */
    public static function forVehicle(?int $makeId, ?int $modelId, ?int $generationId): ?self
    {
        foreach ([['generation_id', $generationId], ['model_id', $modelId], ['make_id', $makeId]] as [$column, $value]) {
            if ($value === null) {
                continue;
            }

            $found = self::query()->active()->where($column, $value)->orderBy('position')->first();

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    public function url(): string
    {
        return route('storefront.collection', $this->slug);
    }

    public function squareImageUrl(): ?string
    {
        return $this->imageUrl($this->square_image_path);
    }

    public function wideImageUrl(): ?string
    {
        return $this->imageUrl($this->wide_image_path);
    }

    /**
     * The garage badge, falling back to the square tile. A collection with only one picture
     * uploaded should still put something next to the car rather than an empty box.
     */
    public function garageImageUrl(): ?string
    {
        return $this->imageUrl($this->garage_image_path) ?? $this->squareImageUrl();
    }

    public function ogImageUrl(): ?string
    {
        return $this->imageUrl($this->og_image_path) ?? $this->wideImageUrl() ?? $this->squareImageUrl();
    }

    /** "1998–2018", "din 2018", "până în 2005", or nothing when neither year is known. */
    public function yearRange(): ?string
    {
        return match (true) {
            $this->year_from && $this->year_to => $this->year_from.'–'.$this->year_to,
            (bool) $this->year_from => 'din '.$this->year_from,
            (bool) $this->year_to => 'până în '.$this->year_to,
            default => null,
        };
    }

    private function imageUrl(?string $path): ?string
    {
        return filled($path) ? Storage::disk('public')->url($path) : null;
    }
}
