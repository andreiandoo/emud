<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductFitment extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['requires_modification' => 'boolean', 'constraints' => 'array']; }
}
