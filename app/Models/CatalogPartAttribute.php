<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogPartAttribute extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['value_json' => 'array'];
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(CatalogPart::class, 'catalog_part_id');
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(CatalogSource::class, 'catalog_source_id');
    }
}
