<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function label(): string
    {
        return trim(implode(' ', array_filter([$this->make?->name, $this->model?->name, $this->generation?->name])));
    }
}
