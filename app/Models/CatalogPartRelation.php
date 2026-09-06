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
}
