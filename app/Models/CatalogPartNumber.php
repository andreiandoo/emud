<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogPartNumber extends Model
{
    protected $guarded = [];

    public function part(): BelongsTo
    {
        return $this->belongsTo(CatalogPart::class, 'catalog_part_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function oeMake(): BelongsTo
    {
        return $this->belongsTo(VehicleMake::class, 'oe_make_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(CatalogSource::class, 'catalog_source_id');
    }
}
