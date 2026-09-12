<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    /**
     * Null on events written before the link existed; those fall back to the `api_redistributable`
     * snapshot they were stored with.
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(CatalogSource::class, 'catalog_source_id');
    }
}
