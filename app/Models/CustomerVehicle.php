<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerVehicle extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['modifications' => 'array', 'is_primary' => 'boolean']; }
    public function make(): BelongsTo { return $this->belongsTo(VehicleMake::class, 'make_id'); }
    public function model(): BelongsTo { return $this->belongsTo(VehicleModel::class, 'model_id'); }
    public function generation(): BelongsTo { return $this->belongsTo(VehicleGeneration::class, 'generation_id'); }
}
