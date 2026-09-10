<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Review extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
            'rating' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(VehicleCollection::class, 'vehicle_collection_id');
    }

    public function make(): BelongsTo
    {
        return $this->belongsTo(VehicleMake::class, 'make_id');
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class, 'model_id');
    }

    /**
     * Published means both the flag and the date: a review scheduled for next week is 'published'
     * but must not be on the page yet, and the storefront asks this scope for everything.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('is_featured')->orderBy('position')->orderByDesc('published_at');
    }

    public function imageUrl(): ?string
    {
        return filled($this->image_path) ? Storage::disk($this->image_disk ?: 'public')->url($this->image_path) : null;
    }

    /** The car the review is about, from whichever of the three sources says something. */
    public function vehicleLabel(): ?string
    {
        if (filled($this->vehicle_label)) {
            return $this->vehicle_label;
        }

        $fromGraph = trim(implode(' ', array_filter([$this->make?->name, $this->model?->name])));

        return $fromGraph !== '' ? $fromGraph : $this->collection?->name;
    }

    /** Handles are stored as the operator typed them; this is what goes in an href. */
    public function instagramUrl(): ?string
    {
        return $this->socialUrl($this->reviewer_instagram, 'https://www.instagram.com/');
    }

    public function facebookUrl(): ?string
    {
        return $this->socialUrl($this->reviewer_facebook, 'https://www.facebook.com/');
    }

    private function socialUrl(?string $handle, string $base): ?string
    {
        $handle = trim((string) $handle);

        if ($handle === '') {
            return null;
        }

        // A pasted profile link is left alone; anything else is treated as a handle, with a
        // leading @ trimmed because that is how people write one.
        return str_starts_with($handle, 'http://') || str_starts_with($handle, 'https://')
            ? $handle
            : $base.ltrim($handle, '@/');
    }
}
