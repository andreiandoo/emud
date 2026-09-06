<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogSearchDocument extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'indexed_at' => 'datetime',
        ];
    }
}
