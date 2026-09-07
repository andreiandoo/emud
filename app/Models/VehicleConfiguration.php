<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleConfiguration extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'production_from' => 'date',
            'production_to' => 'date',
        ];
    }

    public function generation(): BelongsTo
    {
        return $this->belongsTo(VehicleGeneration::class);
    }

    public function engine(): BelongsTo
    {
        return $this->belongsTo(VehicleEngine::class, 'engine_id');
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(VehiclePlatform::class, 'platform_id');
    }

    public function identifiers(): HasMany
    {
        return $this->hasMany(VehicleIdentifier::class, 'configuration_id');
    }

    public function technicalFacts(): HasMany
    {
        return $this->hasMany(VehicleTechnicalFact::class, 'configuration_id');
    }

    public function catalogFitments(): HasMany
    {
        return $this->hasMany(CatalogFitment::class, 'configuration_id');
    }
}
