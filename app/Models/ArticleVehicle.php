<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleVehicle extends Model
{
    protected $guarded = [];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
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

    public function label(): string
    {
        return trim(implode(' ', array_filter([$this->make?->name, $this->model?->name, $this->generation?->name])))
            ?: 'Orice mașină';
    }
}
