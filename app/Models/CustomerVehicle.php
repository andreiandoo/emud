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
