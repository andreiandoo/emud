<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogChangeEvent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'api_redistributable' => 'boolean',
            'occurred_at' => 'datetime',
        ];
    }
}
