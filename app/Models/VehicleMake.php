<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleMake extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function models(): HasMany
    {
        return $this->hasMany(VehicleModel::class, 'make_id');
    }

    /**
     * Makes a customer can actually pick something under. vPIC contributes every registered US
     * manufacturer as reference data for VIN decoding — welding shops and trailer builders
     * included — which left 12,275 of 13,138 makes with no vehicle behind them. They belong in
     * the catalogue, not in a dropdown.
     */
    public function scopeWithConfigurations(Builder $query): Builder
    {
        return $query->whereHas('models.generations.configurations');
    }
}
