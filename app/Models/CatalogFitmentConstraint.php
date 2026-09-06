<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogFitmentConstraint extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['normalized' => 'array'];
    }

    public function fitment(): BelongsTo
    {
        return $this->belongsTo(CatalogFitment::class, 'catalog_fitment_id');
    }
}
