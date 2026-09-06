<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogUnresolvedPartRelation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'resolved_at' => 'datetime',
            'last_resolution_attempt_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function sourcePart(): BelongsTo
    {
        return $this->belongsTo(CatalogPart::class, 'source_part_id');
    }

    public function resolvedTargetPart(): BelongsTo
    {
        return $this->belongsTo(CatalogPart::class, 'resolved_target_part_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(CatalogSource::class, 'catalog_source_id');
    }

    public function sourceRecord(): BelongsTo
    {
        return $this->belongsTo(CatalogSourceRecord::class, 'catalog_source_record_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
