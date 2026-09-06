<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierProductMatchCandidate extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['reasons' => 'array'];
    }

    public function supplierProduct(): BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class);
    }

    public function catalogPart(): BelongsTo
    {
        return $this->belongsTo(CatalogPart::class);
    }
}
