<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogPartRelation extends Model
{
    protected $guarded = [];

    public function sourcePart(): BelongsTo
    {
        return $this->belongsTo(CatalogPart::class, 'source_part_id');
    }

    public function targetPart(): BelongsTo
    {
        return $this->belongsTo(CatalogPart::class, 'target_part_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(CatalogSource::class, 'catalog_source_id');
    }

    public function sourceRecord(): BelongsTo
    {
        return $this->belongsTo(CatalogSourceRecord::class, 'catalog_source_record_id');
    }
}
