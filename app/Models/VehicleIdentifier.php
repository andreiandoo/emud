<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleIdentifier extends Model
{
    protected $guarded = [];

    public function configuration(): BelongsTo
    {
        return $this->belongsTo(VehicleConfiguration::class, 'configuration_id');
    }
}
