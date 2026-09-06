<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogMappingRule extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'target' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
