<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductFitment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['requires_modification' => 'boolean', 'constraints' => 'array'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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

    /** "Suzuki Jimny · III", with whatever parts of it the fitment actually names. */
    public function label(): string
    {
        return implode(' ', array_filter([$this->make?->name, $this->model?->name]))
            .($this->generation?->name ? ' · '.$this->generation->name : '');
    }
}
