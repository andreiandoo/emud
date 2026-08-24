<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleGeneration extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class, 'model_id');
    }

    public function engines(): HasMany
    {
        return $this->hasMany(VehicleEngine::class, 'generation_id');
    }

    public function configurations(): HasMany
    {
        return $this->hasMany(VehicleConfiguration::class, 'generation_id');
    }
}
