<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleManufacturer extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'manufacturer_types' => 'array',
            'metadata' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
