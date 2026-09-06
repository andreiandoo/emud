<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogConflict extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'assertion_ids' => 'array',
            'details' => 'array',
            'resolution' => 'array',
            'resolved_at' => 'datetime',
        ];
    }
}
