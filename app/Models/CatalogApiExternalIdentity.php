<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogApiExternalIdentity extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'last_seen_at' => 'datetime',
        ];
    }

    public function consumer(): BelongsTo
    {
        return $this->belongsTo(CatalogApiConsumer::class, 'catalog_api_consumer_id');
    }
}
