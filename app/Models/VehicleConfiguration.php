<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleConfiguration extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['metadata' => 'array']; }
}
