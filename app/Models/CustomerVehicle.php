<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CustomerVehicle extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'modifications' => 'array',
            'is_primary' => 'boolean',
            'mileage_recorded_on' => 'date',
        ];
    }

    /**
     * The address follows the car. A car saved without one gets one on the way in, and editing
     * it into another model or year moves its page, so the address never names a car it is not.
     */
    protected static function booted(): void
    {
        static::saving(function (CustomerVehicle $vehicle): void {
            if (blank($vehicle->slug) || $vehicle->isDirty(['make_id', 'model_id', 'year'])) {
                $vehicle->slug = $vehicle->uniqueSlug();
            }
        });
    }

    /**
     * Make, model and year — dacia-duster-2018 — with a counter when the same customer keeps two
     * identical cars. Never digits alone: /cont/garaj/7 is the old numbered address, and still
     * answers as one.
     */
    public function uniqueSlug(): string
    {
        $base = Str::slug(trim(implode(' ', array_filter([
            $this->make_id ? VehicleMake::query()->whereKey($this->make_id)->value('name') : null,
            $this->model_id ? VehicleModel::query()->whereKey($this->model_id)->value('name') : null,
            $this->year,
        ])))) ?: 'masina';

        if (ctype_digit($base)) {
            $base = 'masina-'.$base;
        }

        $slug = $base;

        for ($suffix = 2; $this->slugIsTaken($slug); $suffix++) {
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }

    /** The part of the garage page's address that names this car, assigned first if it has none. */
    public function routeSlug(): string
    {
        if (blank($this->slug)) {
            $this->save();
        }

        return (string) $this->slug;
    }

    private function slugIsTaken(string $slug): bool
    {
        return static::query()
            ->where('user_id', $this->user_id)
            ->where('slug', $slug)
            ->when($this->exists, fn ($query) => $query->whereKeyNot($this->getKey()))
            ->exists();
    }

    /**
     * The owner. Needed as a real relation, not just a column: whereBelongsTo() — which is how
     * every ownership check on this model is written — resolves the relationship by name and
     * throws without it.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
     * The editorial collection for this car, when the shop has one. It is what gives a saved
     * vehicle a picture: the graph knows the model, but only a collection has a photo of it.
     */
    public function collection(): BelongsTo
    {
        return $this->belongsTo(VehicleCollection::class, 'vehicle_collection_id');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(VehicleServiceReminder::class);
    }

    public function configuration(): BelongsTo
    {
        return $this->belongsTo(VehicleConfiguration::class, 'configuration_id');
    }

    /** The owner's own photograph, else the collection's picture of the model, else none. */
    public function photoUrl(): ?string
    {
        if ($this->photo_path) {
            return Storage::disk('public')->url($this->photo_path);
        }

        return $this->collection?->garageImageUrl();
    }

    public function label(): string
    {
        return trim(implode(' ', array_filter([$this->make?->name, $this->model?->name, $this->generation?->name])));
    }
}
