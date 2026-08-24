<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductAttributeValue extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['value_json' => 'array', 'value_boolean' => 'boolean', 'value_number' => 'decimal:6'];
    }
}
