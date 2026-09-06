<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogApiConsumer extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'metadata' => 'array',
            'period_started_at' => 'datetime',
        ];
    }

    public function keys(): HasMany
    {
        return $this->hasMany(CatalogApiKey::class);
    }
}
